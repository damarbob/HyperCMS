/*
 * Behaviour of the HyperCMS admin that stays in the browser.
 *
 * htmx asks the server for pieces of HTML. Alpine does the small things a page needs without the server, such as opening
 * the menu on a phone, switching the colour scheme and showing toasts. Both are loaded with `defer`, and this file is
 * loaded before Alpine so that it can register its components.
 */
(function () {
  'use strict';

  var meta = function (name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute('content') : '';
  };

  /* ---- htmx -------------------------------------------------------------------------------------------- */

  document.addEventListener('DOMContentLoaded', function () {
    if (!window.htmx) return;

    // Nothing here needs eval, and a request may only go to this site.
    htmx.config.allowEval = false;
    htmx.config.selfRequestsOnly = true;
    // A 422 holds a form with its errors, so it is swapped in. Other errors are not, and show a toast.
    htmx.config.responseHandling = [
      { code: '204', swap: false },
      { code: '[23]..', swap: true },
      { code: '422', swap: true, error: false },
      { code: '[45]..', swap: false, error: true },
    ];
  });

  document.addEventListener('htmx:configRequest', function (event) {
    var header = meta('csrf-header');
    var token = meta('csrf-token');
    if (header && token) event.detail.headers[header] = token;
  });

  // A bar at the top of the sheet while any request is waiting.
  var pending = 0;
  document.addEventListener('htmx:beforeRequest', function () {
    pending += 1;
    document.body.classList.add('htmx-request');
  });
  ['htmx:afterRequest', 'htmx:sendError', 'htmx:sendAbort'].forEach(function (name) {
    document.addEventListener(name, function () {
      pending = Math.max(0, pending - 1);
      if (pending === 0) document.body.classList.remove('htmx-request');
    });
  });

  function toast(type, message) {
    window.dispatchEvent(new CustomEvent('hyper:toast', { detail: { type: type, message: message } }));
  }

  document.addEventListener('htmx:responseError', function (event) {
    var xhr = event.detail.xhr;
    var text = '';
    try { text = (JSON.parse(xhr.responseText) || {}).message || ''; } catch (e) { /* the body is not JSON */ }
    toast('bad', text || document.documentElement.getAttribute('data-error-text') || 'The request failed.');
  });
  document.addEventListener('htmx:sendError', function () {
    toast('bad', document.documentElement.getAttribute('data-offline-text') || 'The server cannot be reached.');
  });

  // The confirmation dialog of the page, which answers with a promise. `window.HyperUi.confirm('Delete it?')` is for a script
  // that must ask before it acts, and hx-confirm uses it too. Without the dialog it falls back to the browser's own box.
  function confirmDialog(question) {
    var dialog = document.getElementById('confirm-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return Promise.resolve(window.confirm(question));

    return new Promise(function (resolve) {
      dialog.querySelector('[data-confirm-text]').textContent = question;
      var ok = dialog.querySelector('[data-confirm-ok]');
      var cancel = dialog.querySelector('[data-confirm-cancel]');

      function done(accepted) {
        ok.removeEventListener('click', yes);
        cancel.removeEventListener('click', no);
        dialog.removeEventListener('cancel', no);
        dialog.close();
        resolve(accepted);
      }
      function yes() { done(true); }
      function no() { done(false); }

      ok.addEventListener('click', yes);
      cancel.addEventListener('click', no);
      dialog.addEventListener('cancel', no);
      dialog.showModal();
      ok.focus();
    });
  }
  window.HyperUi = { confirm: confirmDialog };

  // hx-confirm opens the confirmation dialog of the page instead of the browser's own box.
  document.addEventListener('htmx:confirm', function (event) {
    var dialog = document.getElementById('confirm-dialog');
    if (!event.detail.question || !dialog || typeof dialog.showModal !== 'function') return;

    event.preventDefault();
    confirmDialog(event.detail.question).then(function (accepted) {
      if (accepted) event.detail.issueRequest(true);
    });
  });

  /* ---- Alpine ------------------------------------------------------------------------------------------ */

  document.addEventListener('alpine:init', function () {
    Alpine.store('toasts', {
      items: [],
      next: 1,
      push: function (type, message) {
        var id = this.next++;
        this.items.push({ id: id, type: type || 'ok', message: message });
        var self = this;
        // An error stays longer, because it asks the person to do something.
        setTimeout(function () { self.remove(id); }, type === 'bad' ? 9000 : 5000);
      },
      remove: function (id) {
        this.items = this.items.filter(function (item) { return item.id !== id; });
      },
    });

    // The server sends a toast with the HX-Trigger header, for example {"hyper:toast":{"type":"ok","message":"Saved"}}.
    window.addEventListener('hyper:toast', function (event) {
      Alpine.store('toasts').push(event.detail.type, event.detail.message);
    });

    // The shell opens and closes the rail on a phone.
    Alpine.data('shell', function () {
      return {
        menu: false,
        toggle: function () { this.menu = !this.menu; },
        close: function () { this.menu = false; },
      };
    });

    // The colour scheme switch. The choice is saved for the user, and the cookie makes the next page render with it.
    Alpine.data('theme', function (initial, url) {
      return {
        choice: initial || 'system',
        set: function (choice) {
          this.choice = choice;
          var root = document.documentElement;
          if (choice === 'light' || choice === 'dark') root.setAttribute('data-theme', choice);
          else root.removeAttribute('data-theme');

          var body = new URLSearchParams({ theme: choice });
          var headers = { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' };
          headers[meta('csrf-header')] = meta('csrf-token');
          fetch(url, { method: 'POST', headers: headers, body: body, credentials: 'same-origin' }).catch(function () { /* the choice still holds for this page */ });
        },
      };
    });

    // A menu that opens from a button, closes on Escape or a click outside, and moves with the arrow keys.
    Alpine.data('menu', function () {
      return {
        open: false,
        toggle: function () { this.open = !this.open; if (this.open) this.$nextTick(this.focusFirst.bind(this)); },
        close: function () { this.open = false; },
        items: function () { return Array.prototype.slice.call(this.$refs.list ? this.$refs.list.querySelectorAll('[role="menuitem"]') : []); },
        focusFirst: function () { var all = this.items(); if (all.length) all[0].focus(); },
        move: function (step) {
          var all = this.items();
          if (!all.length) return;
          var at = all.indexOf(document.activeElement);
          all[(at + step + all.length) % all.length].focus();
        },
      };
    });

    // A toggle that mirrors a checkbox row selection into a counter, for the bar that offers bulk actions.
    Alpine.data('selection', function () {
      return {
        count: 0,
        update: function () { this.count = this.$root.querySelectorAll('tbody input[type="checkbox"]:checked').length; },
        all: function (checked) {
          this.$root.querySelectorAll('tbody input[type="checkbox"]').forEach(function (box) { box.checked = checked; });
          this.update();
        },
      };
    });
  });
})();
