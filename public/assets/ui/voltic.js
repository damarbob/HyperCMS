/*
 * The Voltic chat in the browser. `voltic` is the Alpine component of the screen `admin/voltic`.
 *
 * - A question goes to the server with the last few messages, and the answer comes back as a message and proposals.
 * - A proposal is an action the AI suggests. It does nothing until the person presses its button, which asks the server to run
 *   that one action under the abilities of the person.
 * - The message of the AI may use a little markdown. It is escaped first and then given its bold, lists and links, so nothing
 *   the AI writes can become markup of its own.
 *
 * What the server gives the screen is in the JSON block `#voltic-data`.
 */
(function () {
  'use strict';

  function meta(name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute('content') : '';
  }

  function toast(type, message) {
    window.dispatchEvent(new CustomEvent('hyper:toast', { detail: { type: type, message: message } }));
  }

  function fill(text, values) {
    return String(text || '').replace(/\{(\w+)\}/g, function (all, key) { return key in values ? values[key] : all; });
  }

  function escape(text) {
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // Bold, italic, code and links inside a line. The line is escaped before any of it is added.
  function inline(line) {
    var s = escape(line);
    s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
    s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
    return s;
  }

  // Paragraphs, headings and lists. Anything else is a paragraph.
  function markdown(text) {
    var html = '';
    var list = null;
    function close() { if (list) { html += '</' + list + '>'; list = null; } }
    String(text || '').split(/\r?\n/).forEach(function (raw) {
      var line = raw.trim();
      var bullet = /^(?:[-*•])\s+(.*)$/.exec(line);
      var number = /^\d+[.)]\s+(.*)$/.exec(line);
      var heading = /^#{1,6}\s+(.*)$/.exec(line);
      if (bullet || number) {
        var kind = bullet ? 'ul' : 'ol';
        if (list !== kind) { close(); html += '<' + kind + '>'; list = kind; }
        html += '<li>' + inline((bullet || number)[1]) + '</li>';
      } else {
        close();
        if (heading) html += '<p><strong>' + inline(heading[1]) + '</strong></p>';
        else if (line !== '') html += '<p>' + inline(line) + '</p>';
      }
    });
    close();
    return html;
  }

  var sequence = 0;

  document.addEventListener('alpine:init', function () {
    Alpine.data('voltic', function () {
      var cfg = JSON.parse(document.getElementById('voltic-data').textContent);

      return {
        L: cfg.labels,
        username: cfg.username,
        messages: [],
        turns: [],
        input: '',
        busy: false,

        init: function () {
          this.messages.push({ id: ++sequence, role: 'ai', html: markdown(this.L.introduction), proposals: [], rows: [], links: [] });
        },

        scroll: function () {
          var self = this;
          this.$nextTick(function () { var log = self.$refs.log; if (log) log.scrollTop = log.scrollHeight; });
        },

        headers: function (json) {
          var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
          headers[meta('csrf-header')] = meta('csrf-token');
          if (json) headers['Content-Type'] = 'application/json';
          return headers;
        },

        answer: function (response) {
          return response.json().catch(function () { return {}; }).then(function (body) {
            if (!response.ok || body.status === 'error') throw new Error(body.message || String(response.status));
            return body;
          });
        },

        // Enter sends and Shift+Enter starts a new line.
        keydown: function (event) {
          if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); this.send(); }
        },

        send: function () {
          var text = this.input.trim();
          if (!text || this.busy) return;
          var self = this;
          this.input = '';
          this.messages.push({ id: ++sequence, role: 'user', text: text, proposals: [], rows: [], links: [] });
          this.turns.push({ role: 'user', content: text });
          this.busy = true;
          this.scroll();

          fetch(cfg.urls.ask, { method: 'POST', headers: this.headers(true), body: JSON.stringify({ messages: this.turns.slice(-5) }), credentials: 'same-origin' })
            .then(this.answer)
            .then(function (body) {
              self.messages.push({
                id: ++sequence,
                role: 'ai',
                html: markdown(body.message),
                reasoning: body.reasoning || '',
                model: body.model || '',
                proposals: (body.proposals || []).map(function (p) { return { action: p.action, text: p.text, refusal: p.refusal, state: 'idle', note: '' }; }),
                rows: [],
                links: [],
              });
              if (body.message) self.turns.push({ role: 'assistant', content: body.message });
            })
            .catch(function (error) {
              self.messages.push({ id: ++sequence, role: 'error', text: fill(self.L.volticExperiencingIssuesx, { x: error.message }), proposals: [], rows: [], links: [] });
            })
            .then(function () {
              self.busy = false;
              self.scroll();
              self.$nextTick(function () { if (self.$refs.input) self.$refs.input.focus(); });
            });
        },

        run: function (proposal) {
          if (proposal.state === 'running' || proposal.state === 'done' || proposal.refusal) return;
          var self = this;
          proposal.state = 'running';
          proposal.note = '';
          var body = new FormData();
          body.append('action', JSON.stringify(proposal.action));
          body.append(cfg.csrf.name, cfg.csrf.hash);

          fetch(cfg.urls.run, { method: 'POST', headers: this.headers(false), body: body, credentials: 'same-origin' })
            .then(this.answer)
            .then(function (result) {
              proposal.state = 'done';
              self.messages.push({ id: ++sequence, role: 'system', html: markdown(result.message), proposals: [], rows: result.rows || [], links: result.links || [] });
              self.scroll();
            })
            .catch(function (error) {
              proposal.state = 'idle';
              proposal.note = error.message;
              toast('bad', error.message);
            });
        },
      };
    });
  });
})();
