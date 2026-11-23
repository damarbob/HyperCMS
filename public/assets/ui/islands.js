/*
 * Script islands of the entry form. A box that holds a rich text, or code, is a plain textarea first, so the form works
 * without scripts, and this file turns it into an editor when it can.
 *
 * - `data-island="richtext"` becomes TinyMCE, which is loaded the first time a page has one.
 * - `data-island="code"` stays a textarea, and gets a Tab key that indents instead of leaving the box.
 *
 * Both are set up again for the part of the page htmx swaps in, and an editor is taken down before its box is removed.
 */
(function () {
  'use strict';

  var meta = function (name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute('content') : '';
  };

  var dark = function () {
    var theme = document.documentElement.getAttribute('data-theme');
    return theme === 'dark' || (theme === null && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  };

  var loading = null;
  function loadTinyMce() {
    if (window.tinymce) return Promise.resolve();
    if (!loading) {
      loading = new Promise(function (resolve, reject) {
        var script = document.createElement('script');
        script.src = meta('assets-base') + 'vendor/tinymce/tinymce.min.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
      });
    }
    return loading;
  }

  function richText(box) {
    loadTinyMce().then(function () {
      var base = meta('assets-base') + 'vendor/tinymce';
      window.tinymce.init({
        target: box,
        license_key: 'gpl',
        base_url: base,
        suffix: '.min',
        skin: dark() ? 'oxide-dark' : 'oxide',
        content_css: dark() ? 'dark' : 'default',
        menubar: false,
        branding: false,
        promotion: false,
        height: 380,
        relative_urls: false,
        document_base_url: meta('site-base'),
        plugins: 'advlist autolink lists link image media charmap searchreplace code fullscreen table wordcount',
        toolbar: 'blocks | bold italic underline | bullist numlist | link image media table | code fullscreen',
        // The Browse button of the link, image and media dialogs opens the media library, when its script is on the page.
        file_picker_types: 'file image media',
        file_picker_callback: window.HyperMedia ? function (callback, value, info) {
          var accept = info.filetype === 'image' ? 'image' : (info.filetype === 'media' ? 'video' : 'any');
          window.HyperMedia.pick({ accept: accept, multiple: false }).then(function (files) {
            if (files.length) callback(files[0].path, { text: files[0].name, alt: files[0].name, title: files[0].name });
          });
        } : undefined,
        setup: function (editor) {
          // The form must learn that the box changed, and the textarea must hold the text when the form is sent.
          editor.on('input change keyup undo redo', function () {
            editor.save();
            box.dispatchEvent(new Event('input', { bubbles: true }));
          });
        },
      });
    });
  }

  function code(box) {
    var free = false;
    box.addEventListener('keydown', function (event) {
      // Escape followed by Tab leaves the box, so a keyboard user is never trapped in it.
      if (event.key === 'Escape') {
        free = true;
        return;
      }
      if (event.key !== 'Tab' || event.shiftKey || event.ctrlKey || event.metaKey || event.altKey) {
        free = false;
        return;
      }
      if (free) {
        free = false;
        return;
      }
      event.preventDefault();
      var start = box.selectionStart;
      box.value = box.value.slice(0, start) + '  ' + box.value.slice(box.selectionEnd);
      box.selectionStart = box.selectionEnd = start + 2;
      box.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  function setUp(root) {
    root.querySelectorAll('textarea[data-island]').forEach(function (box) {
      if (box.dataset.islandReady) return;
      box.dataset.islandReady = '1';
      if (box.dataset.island === 'richtext') richText(box);
      else if (box.dataset.island === 'code') code(box);
    });
  }

  document.addEventListener('DOMContentLoaded', function () { setUp(document); });
  document.addEventListener('htmx:afterSwap', function (event) { setUp(event.target); });

  // Before a request, every editor writes its text back into its textarea.
  document.addEventListener('htmx:beforeRequest', function () {
    if (window.tinymce) window.tinymce.triggerSave();
  });

  // Take an editor down before htmx removes its box, so none is left holding a box that is gone.
  document.addEventListener('htmx:beforeCleanupElement', function (event) {
    var box = event.target;
    if (window.tinymce && box.matches && box.matches('textarea[data-island="richtext"]') && box.id) {
      var editor = window.tinymce.get(box.id);
      if (editor) editor.remove();
    }
  });
})();
