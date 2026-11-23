/*
 * What the page editor island needs from the page around it. The editor was written for an admin that loaded a global
 * `window.hyper` with a dialog library, and it still asks that object for three things, so this file gives it those and
 * draws them with the toasts and the dialog element of the admin.
 *
 * `window.hyper.config`, `lang` and `data` are written into the page by the server before this file runs.
 */
(function () {
  'use strict';

  var hyper = (window.hyper = window.hyper || {});
  hyper.util = hyper.util || {};
  hyper.factory = hyper.factory || {};

  function toast(type, title, text) {
    var message = [title, text].filter(Boolean).join(' ');
    window.dispatchEvent(new CustomEvent('hyper:toast', { detail: { type: type, message: message } }));
  }

  /* Are two addresses the same place? Only the scheme, the host and the port count, which is what the library frame needs
     to be sure a message comes from this site. */
  hyper.util.uri = hyper.util.uri || {
    areUrisEqual: function (a, b) {
      try {
        return new URL(a, window.location.href).origin === new URL(b, window.location.href).origin;
      } catch (e) {
        return false;
      }
    },
  };

  /* A small stand-in for the dialog library: a toast for the answer, and a busy bar while a request is open. */
  var open = 0;
  hyper.factory.swal = hyper.factory.swal || {
    loader: function () {
      open += 1;
      document.body.classList.add('htmx-request');
    },
    get: function () {
      return {
        close: function () {
          open = Math.max(0, open - 1);
          if (open === 0) document.body.classList.remove('htmx-request');
        },
      };
    },
    success: function (title, options) { toast('ok', title, options && options.text); },
    error: function (title, options) {
      var text = options && options.text;
      toast('bad', typeof title === 'string' ? title : '', typeof text === 'string' ? text : '');
    },
  };
})();
