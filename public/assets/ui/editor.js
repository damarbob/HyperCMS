/*
 * The page editor. It starts GrapesJS with its own panels, joins the bar above it to the editor, and saves.
 *
 * What GrapesJS needs comes in the JSON block `#editor-data`, written by the server. The panels are not touched here: they
 * are GrapesJS's, and `editor.css` only gives them the colours of the admin.
 *
 * It is a module, so it runs after the plugin files of the page, which are classic scripts and modules before it.
 */
(function () {
  'use strict';

  var data = JSON.parse(document.getElementById('editor-data').textContent);
  var L = data.labels;

  function meta(name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute('content') : '';
  }

  function toast(type, message) {
    window.dispatchEvent(new CustomEvent('hyper:toast', { detail: { type: type, message: message } }));
  }

  /* ---- The page ---------------------------------------------------------------------------------------------- */

  var project = null;
  try { project = JSON.parse(data.project || 'null'); } catch (e) { console.error('The stored page could not be read, so the editor starts empty.', e); }
  if (!project || (Array.isArray(project) && project.length === 0)) {
    project = { pages: [{ component: '<h1 style="text-align:center">' + L.hello + '</h1>' }] };
  }

  var dark = document.documentElement.getAttribute('data-theme') === 'dark'
    || (document.documentElement.getAttribute('data-theme') === null && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);

  /* ---- A meta tag, with the traits that make it read a field of an entry ---------------------------------------- */

  function metaTagPlugin(editor) {
    var query = ['model-id', 'find-field', 'find-value', 'return-field'].map(function (name) {
      return { type: 'text', name: 'data-hyper-query-' + name, label: L['query_' + name.replace(/-/g, '_')] || name };
    });
    editor.Components.addType('meta-tag', {
      isComponent: function (el) { return el.tagName === 'META' ? { type: 'meta-tag' } : false; },
      model: {
        defaults: {
          tagName: 'meta',
          name: 'Meta',
          droppable: false,
          traits: [{ type: 'text', name: 'name' }, { type: 'text', name: 'property' }, { type: 'text', name: 'content' }].concat(query),
        },
      },
    });
  }

  /* ---- The editor --------------------------------------------------------------------------------------------- */

  var plugins = [
    'grapesjs-parser-postcss', 'grapesjs-custom-code', 'grapesjs-tui-image-editor', 'grapesjs-style-gradient', 'grapesjs-style-bg', 'grapesjs-style-filter',
    'grapesjs-blocks-bootstrap-5', 'grapesjs-bootstrap-grid-system', 'grapesjs-bootstrap-typography', 'grapesjs-bootstrap-button', 'grapesjs-bootstrap-navbar',
    'grapesjs-bootstrap-card', 'grapesjs-bootstrap-accordion', 'grapesjs-bootstrap-carousel', 'grapesjs-bootstrap-forms', 'grapesjs-bootstrap-badge',
    'grapesjs-bootstrap-breadcrumb', 'grapesjs-bootstrap-alert', 'grapesjs-bootstrap-dropdown', metaTagPlugin,
  ];

  var csrfParams = {};
  csrfParams[data.csrf.name] = data.csrf.hash;

  var editor = window.grapesjs.init({
    container: '#gjs',
    height: '100%',
    width: 'auto',
    fromElement: false,
    storageManager: false,
    projectData: project,
    plugins: plugins,
    pluginsOpts: {
      'grapesjs-blocks-bootstrap-5': { blocks: { container: false, row: false, column: false, card: false } },
      'grapesjs-custom-code': { propsCustomCode: { attributes: { 'data-gjs-type': 'custom-code' } } },
      'grapesjs-tui-image-editor': { upload: true },
    },
    canvas: { styles: data.canvas.styles.head, scripts: data.canvas.scripts.head },
    selectorManager: { componentFirst: true },
    deviceManager: {
      devices: [
        { id: 'desktop', name: L.desktop, width: '' },
        { id: 'tablet', name: L.tablet, width: '768px', widthMedia: '992px' },
        { id: 'mobile', name: L.mobile, width: '375px', widthMedia: '480px' },
      ],
    },
    assetManager: {
      upload: data.upload,
      uploadName: 'files',
      params: csrfParams,
      // Images are chosen from the media library, which also takes uploads. The editor's own dialog is not shown.
      custom: {
        open: function (props) {
          if (!window.HyperMedia) { toast('bad', L.libraryMissing); props.close(); return; }
          window.HyperMedia.pick({ accept: 'image', multiple: false, title: L.chooseImage }).then(function (files) {
            if (files.length) props.select({ type: 'image', src: files[0].url, name: files[0].name }, true);
            else props.close();
          });
        },
        close: function () {},
      },
    },
  });

  /* ---- When the editor has loaded ---------------------------------------------------------------------------- */

  editor.on('load', function () {
    // The bar above the editor has the device buttons, so the device selector of GrapesJS would be a second one.
    editor.Panels.removePanel('devices-c');

    // The scripts that sites add to every page, which the canvas runs so the page looks as it will.
    var doc = editor.Canvas.getDocument();
    (data.canvas.scripts.body || []).forEach(function (url) {
      var script = doc.createElement('script');
      script.src = url;
      script.setAttribute('data-no-export', 'true');
      doc.body.appendChild(script);
    });
  });

  /* ---- The bar above the editor ------------------------------------------------------------------------------- */

  var devices = document.getElementById('editor-devices');
  devices.addEventListener('click', function (event) {
    var button = event.target.closest('button[data-device]');
    if (!button) return;
    editor.setDevice(button.getAttribute('data-device'));
    devices.querySelectorAll('button').forEach(function (b) { b.setAttribute('aria-pressed', b === button ? 'true' : 'false'); });
  });

  var undo = document.getElementById('editor-undo');
  var redo = document.getElementById('editor-redo');
  var state = document.getElementById('editor-state');
  // The count that GrapesJS keeps does not move for every change, so the editor keeps its own: any update after the load.
  var changed = false;
  var loaded = false;
  editor.on('load', function () { loaded = true; });
  function history() {
    undo.disabled = !editor.UndoManager.hasUndo();
    redo.disabled = !editor.UndoManager.hasRedo();
    state.textContent = changed ? L.unsaved : '';
  }
  undo.addEventListener('click', function () { editor.UndoManager.undo(); history(); });
  redo.addEventListener('click', function () { editor.UndoManager.redo(); history(); });
  editor.on('update undo redo', function () { if (loaded) changed = true; history(); });

  window.addEventListener('beforeunload', function (event) {
    if (changed) { event.preventDefault(); event.returnValue = ''; }
  });

  /* ---- Import: paste HTML, with its style, in place of the page ------------------------------------------------- */

  editor.Commands.add('hyper-import', {
    run: function (ed) {
      var box = document.createElement('div');
      var area = document.createElement('textarea');
      area.className = 'editor-import';
      area.setAttribute('aria-label', L.import);
      area.placeholder = L.importHelp;
      var go = document.createElement('button');
      go.type = 'button';
      go.className = 'btn primary';
      go.textContent = L.import;
      go.addEventListener('click', function () {
        if (area.value.trim() === '') return;
        ed.setComponents(area.value);
        ed.Modal.close();
        history();
      });
      box.appendChild(area);
      box.appendChild(go);
      ed.Modal.open({ title: L.import, content: box });
    },
  });
  editor.Panels.addButton('options', { id: 'hyper-import', className: 'fa fa-download', command: 'hyper-import', attributes: { title: L.import } });

  /* ---- Save --------------------------------------------------------------------------------------------------- */

  var saveButton = document.getElementById('editor-save');
  function save() {
    if (saveButton.disabled) return;
    saveButton.disabled = true;
    saveButton.classList.add('htmx-request');

    // What the editor draws only to help the person, such as an empty-block hint, is marked, and is left out.
    var doc = new DOMParser().parseFromString(editor.getHtml(), 'text/html');
    doc.querySelectorAll('[data-no-export]').forEach(function (el) { el.parentNode.removeChild(el); });

    var body = new FormData();
    body.append('html', doc.documentElement.outerHTML);
    body.append('css', editor.getCss());
    body.append('components', JSON.stringify(editor.getComponents()));
    body.append('project', JSON.stringify(editor.getProjectData()));
    body.append(data.csrf.name, data.csrf.hash);

    var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[meta('csrf-header')] = meta('csrf-token');

    fetch(data.save, { method: 'POST', headers: headers, body: body, credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (answer) {
        if (answer.success) {
          changed = false;
          history();
          toast('ok', answer.success);
        } else {
          toast('bad', answer.error || L.saveFailed);
        }
      })
      .catch(function () { toast('bad', L.saveFailed); })
      .then(function () {
        saveButton.disabled = false;
        saveButton.classList.remove('htmx-request');
      });
  }
  saveButton.addEventListener('click', save);
  document.addEventListener('keydown', function (event) {
    if ((event.ctrlKey || event.metaKey) && event.key === 's') { event.preventDefault(); save(); }
  });

  history();
  window.hyperEditor = editor;
})();
