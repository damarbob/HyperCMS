/*
 * The media library in the browser.
 *
 * - `mediaBrowser` is the Alpine component of the library screen and of the picker dialog. It counts the ticked files,
 *   sends the files that are dropped or chosen to the server one at a time, and asks the server for the library again.
 * - `window.HyperMedia.pick()` opens the library in a dialog and answers with the files the person chose. The file field
 *   of the entry form, the rich text editor and the page editor use it.
 * - A `select[data-media-field]` holds the ids of the chosen files. This script hides it and draws the chosen files as
 *   chips with a button that opens the picker, so the form posts the same select with or without the script.
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

  /* ---- The library ------------------------------------------------------------------------------------- */

  document.addEventListener('alpine:init', function () {
    Alpine.data('mediaBrowser', function (config) {
      return {
        count: 0,
        dragging: false,
        status: '',

        init: function () { this.update(); },

        boxes: function () { return Array.prototype.slice.call(this.$root.querySelectorAll('input[name="ids[]"]')); },

        // A single choice behaves like radio buttons, a multiple one like checkboxes.
        update: function (event) {
          var boxes = this.boxes();
          if (config.picker && !config.multiple && event && event.target && event.target.checked) {
            boxes.forEach(function (box) { if (box !== event.target) box.checked = false; });
          }
          this.count = boxes.filter(function (box) { return box.checked; }).length;
        },

        selected: function () {
          return this.boxes().filter(function (box) { return box.checked; }).map(function (box) {
            try { return JSON.parse(box.getAttribute('data-file')); } catch (e) { return null; }
          }).filter(Boolean);
        },

        selectedText: function () { return fill(config.texts.selected, { count: this.count }); },

        choose: function () { this.$dispatch('media-choose', { files: this.selected() }); },

        ask: function (name) {
          var dialog = this.$refs[name];
          if (!dialog) return;
          var input = dialog.querySelector('input');
          if (name === 'renameFile') {
            var file = this.selected()[0];
            if (file && input) input.value = file.name;
          } else if (name === 'newFolder' && input) {
            input.value = '';
          }
          dialog.showModal();
          if (input) {
            input.focus();
            // The name without its extension is what a person usually changes.
            var dot = input.value.lastIndexOf('.');
            if (name === 'renameFile' && dot > 0) input.setSelectionRange(0, dot); else input.select();
          }
        },

        copyLink: function () {
          var file = this.selected()[0];
          if (!file) return;
          var done = function () { toast('ok', config.texts.linkCopied); };
          if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(file.url).then(done);
          else window.prompt('', file.url);
        },

        // Files go one request each, so a big batch shows its progress and one refused file does not stop the others.
        upload: function (list) {
          var self = this;
          var files = Array.prototype.slice.call(list || []);
          if (!files.length) return;

          var headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
          headers[meta('csrf-header')] = meta('csrf-token');
          var done = 0;
          var stored = 0;
          var rootId = this.$root.id;

          function next() {
            if (done >= files.length) {
              self.status = '';
              if (stored) toast('ok', stored === 1 ? config.texts.uploadedOne : fill(config.texts.uploaded, { count: stored }));
              if (window.htmx) window.htmx.ajax('GET', config.refresh, { target: '#' + rootId, swap: 'outerHTML' });
              return Promise.resolve();
            }
            self.status = fill(config.texts.uploading, { done: done + 1, total: files.length });

            var body = new FormData();
            body.append('folder', config.folder);
            body.append('files[]', files[done]);
            var name = files[done].name;
            done += 1;

            return fetch(config.upload, { method: 'POST', body: body, headers: headers, credentials: 'same-origin' })
              .then(function (response) { return response.json(); })
              .then(function (answer) {
                stored += (answer.files || []).length;
                (answer.problems || []).forEach(function (problem) { toast('bad', problem); });
              })
              .catch(function () { toast('bad', fill(config.texts.failed, { name: name })); })
              .then(next);
          }

          next();
        },
      };
    });
  });

  /* ---- The picker dialog ------------------------------------------------------------------------------- */

  window.HyperMedia = {
    /**
     * Open the library in a dialog.
     *
     * @param {{accept?: string, multiple?: boolean, title?: string}} options
     * @returns {Promise<Array<{id:number,name:string,url:string,path:string,mime:string,kind:string}>>} The chosen files, or an empty list.
     */
    pick: function (options) {
      options = options || {};
      return new Promise(function (resolve) {
        var dialog = document.createElement('dialog');
        dialog.className = 'modal media-dialog';
        var title = options.title || document.documentElement.getAttribute('data-media-title') || 'Media';
        dialog.setAttribute('aria-label', title);

        var head = document.createElement('div');
        head.className = 'head';
        var h2 = document.createElement('h2');
        h2.textContent = title;
        head.appendChild(h2);
        var body = document.createElement('div');
        body.className = 'content media-dialog-body';
        dialog.appendChild(head);
        dialog.appendChild(body);
        document.body.appendChild(dialog);

        var finished = false;
        function finish(files) {
          if (finished) return;
          finished = true;
          if (dialog.open) dialog.close();
          dialog.remove();
          resolve(files);
        }
        dialog.addEventListener('media-choose', function (event) { finish(event.detail.files || []); });
        dialog.addEventListener('media-cancel', function () { finish([]); });
        dialog.addEventListener('cancel', function () { finish([]); });

        var query = [];
        if (options.accept && options.accept !== 'any') query.push('accept=' + encodeURIComponent(options.accept));
        if (options.multiple) query.push('multiple=1');
        var url = meta('site-base') + 'admin/media/picker' + (query.length ? '?' + query.join('&') : '');

        dialog.showModal();
        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (response) {
            if (!response.ok) throw new Error('The library could not be opened.');
            return response.text();
          })
          .then(function (html) {
            body.innerHTML = html;
            if (window.htmx) window.htmx.process(body);
          })
          .catch(function (error) {
            toast('bad', error.message);
            finish([]);
          });
      });
    },
  };

  /* ---- The file field of a form ------------------------------------------------------------------------ */

  function enhance(select) {
    var many = select.multiple;
    var root = document.createElement('div');
    root.className = 'media-field';
    var chips = document.createElement('div');
    chips.className = 'chips';
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn small';
    button.textContent = select.getAttribute('data-choose') || 'Choose';
    root.appendChild(chips);
    root.appendChild(button);
    select.hidden = true;
    select.parentNode.insertBefore(root, select);
    if (select.id) {
      select.id = select.id + '-select';
      button.id = select.id.replace(/-select$/, '');
    }

    function changed() {
      select.dispatchEvent(new Event('change', { bubbles: true }));
      select.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function draw() {
      chips.textContent = '';
      Array.prototype.filter.call(select.options, function (o) { return o.selected && o.value !== ''; }).forEach(function (option) {
        var chip = document.createElement('span');
        chip.className = 'chip';
        var url = option.getAttribute('data-url');
        if (url) {
          var link = document.createElement('a');
          link.href = url;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.textContent = option.textContent;
          chip.appendChild(link);
        } else {
          chip.appendChild(document.createTextNode(option.textContent));
        }
        var x = document.createElement('button');
        x.type = 'button';
        x.className = 'x';
        x.textContent = '×';
        x.setAttribute('aria-label', (select.getAttribute('data-remove') || '').replace('{name}', option.textContent));
        x.addEventListener('click', function () {
          option.selected = false;
          if (!many) option.remove();
          draw();
          changed();
          button.focus();
        });
        chip.appendChild(x);
        chips.appendChild(chip);
      });
      // A single file is its chip, so the button is only needed to change it.
      button.textContent = select.getAttribute(many || !chips.childNodes.length ? 'data-choose' : 'data-change') || button.textContent;
    }

    button.addEventListener('click', function () {
      window.HyperMedia.pick({ accept: select.getAttribute('data-accept') || 'any', multiple: many, title: select.getAttribute('data-title') || '' }).then(function (files) {
        if (!files.length) return;
        if (!many) Array.prototype.forEach.call(select.options, function (o) { o.selected = false; });
        files.forEach(function (file) {
          var option = Array.prototype.filter.call(select.options, function (o) { return o.value === String(file.id); })[0];
          if (!option) {
            option = document.createElement('option');
            option.value = String(file.id);
            select.appendChild(option);
          }
          option.textContent = file.name;
          option.setAttribute('data-url', file.url);
          option.selected = true;
        });
        draw();
        changed();
      });
    });

    draw();
  }

  function setUp(root) {
    root.querySelectorAll('select[data-media-field]').forEach(function (select) {
      if (select.dataset.mediaReady) return;
      select.dataset.mediaReady = '1';
      enhance(select);
    });
  }

  document.addEventListener('DOMContentLoaded', function () { setUp(document); });
  document.addEventListener('htmx:afterSwap', function (event) { setUp(event.target); });
})();
