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

  /* ---- The models and their fields, which the person can pick from ---------------------------------------------- */

  // The list comes from the models API, which answers a signed-in editor with the models that person may read. If it cannot be
  // had, the lists are empty and the names are typed, as they were before the lists existed.
  var modelList = null;
  function models() {
    if (!modelList) {
      var body = new FormData();
      body.append('draw', '1');
      body.append('length', '-1');
      var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      headers[meta('csrf-header')] = meta('csrf-token');
      modelList = fetch(data.models, { method: 'POST', headers: headers, body: body, credentials: 'same-origin' })
        .then(function (response) { if (!response.ok) throw new Error(String(response.status)); return response.json(); })
        .then(function (answer) {
          return (answer.data || []).map(function (model) {
            var fields = [];
            try { fields = typeof model.fields === 'string' ? JSON.parse(model.fields) : model.fields; } catch (e) { fields = []; }
            return {
              id: String(model.id),
              name: String(model.name),
              fields: (Array.isArray(fields) ? fields : []).filter(function (f) { return f && f.id; }).map(function (f) { return { id: String(f.id), label: String(f.label || f.id) }; }),
            };
          });
        })
        .catch(function (error) { console.error('The models could not be listed, so their names are typed.', error); modelList = null; return []; });
    }
    return modelList;
  }

  var pickCount = 0;
  var MODEL_ATTRIBUTE = 'data-hyper-query-model-id';

  // A text field that suggests names as the person types. `source` is `models`, or `fields` for the fields of the chosen model.
  function pickPlugin(editor) {
    editor.Traits.addType('hyper-pick', {
      eventCapture: [],
      onEvent: function () {},
      createInput: function (opts) {
        var component = opts.component;
        var name = opts.trait.get('name');
        var source = opts.trait.get('source');
        var wrap = make('span', 'editor-pick');
        var input = make('input');
        var suggestions = make('datalist');
        suggestions.id = 'hyper-pick-' + (++pickCount);
        input.setAttribute('list', suggestions.id);
        input.setAttribute('autocomplete', 'off');
        wrap.appendChild(input);
        wrap.appendChild(suggestions);

        function current(attribute) { var all = component.get('attributes') || {}; return all[attribute] === undefined ? '' : String(all[attribute]); }
        function suggest() {
          models().then(function (all) {
            var items = source === 'models'
              ? all.map(function (m) { return { value: m.id, label: m.name }; })
              : ((all.filter(function (m) { return m.id === current(MODEL_ATTRIBUTE); })[0] || {}).fields || []).map(function (f) { return { value: f.id, label: f.label }; });
            suggestions.textContent = '';
            items.forEach(function (item) {
              var option = document.createElement('option');
              option.value = item.value;
              option.label = item.label;
              suggestions.appendChild(option);
            });
          });
        }
        input.value = current(name);
        suggest();
        input.addEventListener('change', function () {
          var next = {};
          next[name] = input.value.trim();
          component.addAttributes(next);
        });

        // Another choice, or an undo, changes the attributes, and the fields to suggest follow the model.
        function refresh() {
          if (!wrap.isConnected) { component.off('change:attributes', refresh); return; }
          if (document.activeElement !== input) input.value = current(name);
          suggest();
        }
        component.on('change:attributes', refresh);
        return wrap;
      },
    });
  }

  /* ---- A meta tag, with the traits that make it read a field of an entry ---------------------------------------- */

  var ENTRY_ICON = '<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M448 80v48c0 44.2-100.3 80-224 80S0 172.2 0 128V80C0 35.8 100.3 0 224 0s224 35.8 224 80zM393.2 214.7c20.8-7.4 39.9-16.9 54.8-28.6V288c0 44.2-100.3 80-224 80S0 332.2 0 288V186.1c14.9 11.8 34 21.2 54.8 28.6C99.7 230.7 159.5 240 224 240s124.3-9.3 169.2-25.3zM0 346.1c14.9 11.8 34 21.2 54.8 28.6C99.7 390.7 159.5 400 224 400s124.3-9.3 169.2-25.3c20.8-7.4 39.9-16.9 54.8-28.6V432c0 44.2-100.3 80-224 80S0 476.2 0 432V346.1z"/></svg>';

  function metaTagPlugin(editor) {
    var label = function (name) { return L['query_' + name.replace(/-/g, '_')] || name; };
    var query = [
      { type: 'hyper-pick', source: 'models', name: MODEL_ATTRIBUTE, label: label('model-id') },
      { type: 'hyper-pick', source: 'fields', name: 'data-hyper-query-find-field', label: label('find-field') },
      { type: 'text', name: 'data-hyper-query-find-value', label: label('find-value'), placeholder: '{uri-query-param}' },
      { type: 'hyper-pick', source: 'fields', name: 'data-hyper-query-return-field', label: label('return-field') },
    ];
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

    // A meta tag that is ready to ask for a field of an entry. The server fills its content when the page is shown.
    editor.Blocks.add('hyper-entry-value', {
      label: L.entryValue,
      category: 'Hyper',
      media: ENTRY_ICON,
      content: {
        type: 'meta-tag',
        attributes: { name: 'description', content: '', 'data-hyper-query-model-id': '', 'data-hyper-query-find-field': '', 'data-hyper-query-find-value': '', 'data-hyper-query-return-field': '' },
      },
    });
  }

  /* ---- Any attribute of the selected element, in the settings panel --------------------------------------------- */

  // The id, the classes and the style have their own places in the editor, and the `data-gjs-` ones are the editor's own. The
  // `data-hyper-query-` ones of a meta tag are chosen with the pickers. A name that starts with two underscores is a note
  // that GrapesJS keeps for itself.
  function hiddenAttribute(name) {
    return name === 'id' || name === 'class' || name === 'style' || name.indexOf('data-gjs-') === 0 || name.indexOf('data-hyper-query-') === 0 || name.indexOf('__') === 0;
  }
  var ATTRIBUTE_NAME = /^[A-Za-z_:][-A-Za-z0-9_:.]*$/;

  function make(tag, className, text) {
    var el = document.createElement(tag);
    if (className) el.className = className;
    if (text) el.textContent = text;
    return el;
  }

  function attributesPlugin(editor) {
    editor.Traits.addType('hyper-attributes', {
      eventCapture: [],
      noLabel: true,
      onEvent: function () {},
      createInput: function (opts) {
        var component = opts.component;
        var box = make('div', 'editor-attrs');
        box.appendChild(make('div', 'editor-attrs-title', L.attributes));
        var list = make('div', 'editor-attrs-list');
        var add = make('button', 'btn', L.addAttribute);
        add.type = 'button';
        box.appendChild(list);
        box.appendChild(add);
        var writing = false;

        // The rows are written to the element each time one changes. A value that was not touched keeps its type, so a
        // flag such as `controls` is not turned into the text "true".
        function write() {
          var raw = component.get('attributes') || {};
          var next = {};
          Object.keys(raw).forEach(function (name) { if (hiddenAttribute(name)) next[name] = raw[name]; });
          var taken = {};
          list.querySelectorAll('.editor-attr').forEach(function (row) {
            var name = row.querySelector('[data-attr="name"]').value.trim();
            var value = row.querySelector('[data-attr="value"]').value;
            var bad = name !== '' && (!ATTRIBUTE_NAME.test(name) || hiddenAttribute(name) || taken[name]);
            row.setAttribute('data-bad', bad ? 'true' : 'false');
            if (name === '' || bad) return;
            taken[name] = true;
            next[name] = raw[name] !== undefined && String(raw[name]) === value ? raw[name] : value;
          });
          writing = true;
          component.setAttributes(next);
          writing = false;
        }

        function row(name, value) {
          var item = make('div', 'editor-attr');
          var key = make('input', 'input');
          key.setAttribute('data-attr', 'name');
          key.placeholder = L.attributeName;
          key.setAttribute('aria-label', L.attributeName);
          key.value = name;
          var val = make('input', 'input');
          val.setAttribute('data-attr', 'value');
          val.placeholder = L.attributeValue;
          val.setAttribute('aria-label', L.attributeValue);
          val.value = value;
          var drop = make('button', 'btn quiet icon-only', '×');
          drop.type = 'button';
          drop.setAttribute('aria-label', L.removeAttribute);
          drop.title = L.removeAttribute;
          drop.addEventListener('click', function () { list.removeChild(item); write(); });
          key.addEventListener('change', write);
          val.addEventListener('change', write);
          item.appendChild(key);
          item.appendChild(val);
          item.appendChild(drop);
          list.appendChild(item);
          return key;
        }

        function fill() {
          list.textContent = '';
          var raw = component.get('attributes') || {};
          Object.keys(raw).forEach(function (name) {
            if (!hiddenAttribute(name)) row(name, String(raw[name]));
          });
        }
        fill();
        add.addEventListener('click', function () { row('', '').focus(); });

        // An undo or a change from another part of the editor shows here too, unless this panel is the one writing.
        function refresh() {
          if (!box.isConnected) { component.off('change:attributes', refresh); return; }
          if (!writing) fill();
        }
        component.on('change:attributes', refresh);
        return box;
      },
    });

    editor.on('component:selected', function (component) {
      if (component && !component.getTrait('hyper-attributes')) {
        component.addTrait({ type: 'hyper-attributes', name: 'hyper-attributes', label: L.attributes });
      }
    });
  }

  /* ---- The editor --------------------------------------------------------------------------------------------- */

  var plugins = [
    'grapesjs-parser-postcss', 'grapesjs-custom-code', 'grapesjs-tui-image-editor', 'grapesjs-style-gradient', 'grapesjs-style-bg', 'grapesjs-style-filter',
    'grapesjs-blocks-bootstrap-5', 'grapesjs-bootstrap-grid-system', 'grapesjs-bootstrap-typography', 'grapesjs-bootstrap-button', 'grapesjs-bootstrap-navbar',
    'grapesjs-bootstrap-card', 'grapesjs-bootstrap-accordion', 'grapesjs-bootstrap-carousel', 'grapesjs-bootstrap-forms', 'grapesjs-bootstrap-badge',
    'grapesjs-bootstrap-breadcrumb', 'grapesjs-bootstrap-alert', 'grapesjs-bootstrap-dropdown', pickPlugin, metaTagPlugin, attributesPlugin,
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
        { id: 'mobileLandscape', name: L.mobileLandscape, width: '568px', widthMedia: '768px' },
        { id: 'mobilePortrait', name: L.mobilePortrait, width: '375px', widthMedia: '480px' },
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

  // Filters are in the style manager, in the section of the extras, besides the ones it has.
  editor.StyleManager.addProperty('extra', { extend: 'filter' });
  editor.StyleManager.addProperty('extra', { extend: 'filter', property: 'backdrop-filter' });

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

  /* ---- A search above the blocks -------------------------------------------------------------------------------- */

  // It keeps the blocks that have the words in their name or in the name of their group. The panel of the blocks is drawn the
  // first time it is opened, so the box is put in then.
  function plain(text) { return String(text || '').replace(/<[^>]*>/g, '').toLowerCase(); }
  editor.on('run:open-blocks', function () {
    var holder = editor.getContainer().querySelector('.gjs-blocks-cs');
    // Drawing the blocks again replaces what is in the holder, so the box goes beside it, in the panel that holds it.
    if (!holder || holder.parentNode.querySelector('.editor-search')) return;
    var search = document.createElement('div');
    search.className = 'editor-search';
    var field = document.createElement('input');
    field.type = 'search';
    field.className = 'input';
    field.placeholder = L.searchBlocks;
    field.setAttribute('aria-label', L.searchBlocks);
    search.appendChild(field);
    holder.parentNode.insertBefore(search, holder);
    field.addEventListener('input', function () {
      var words = field.value.trim().toLowerCase();
      if (words === '') { editor.Blocks.render(); return; }
      editor.Blocks.render(editor.Blocks.getAll().filter(function (block) {
        return plain(block.getLabel()).indexOf(words) !== -1 || plain(block.getCategoryLabel()).indexOf(words) !== -1;
      }));
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
      // It opens with the page as it is, so a part of it can be changed and put back.
      area.value = ed.getHtml() + '<style>' + ed.getCss({ avoidProtected: true }) + '</style>';
      var go = document.createElement('button');
      go.type = 'button';
      go.className = 'btn primary';
      go.textContent = L.import;
      go.addEventListener('click', function () {
        if (area.value.trim() === '') return;
        ed.Css.clear();
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

  /* ---- The files of the site, opened from the editor ------------------------------------------------------------ */

  // The library opens as a dialog to look through, upload to and tidy. What is chosen in it is not used here.
  editor.Panels.addButton('options', {
    id: 'hyper-files',
    className: 'fa fa-folder-open',
    togglable: false,
    command: function () {
      if (!window.HyperMedia) { toast('bad', L.libraryMissing); return; }
      window.HyperMedia.pick({ accept: 'any', multiple: true, title: L.files });
    },
    attributes: { title: L.files },
  });

  /* ---- Styling by class instead of by element ------------------------------------------------------------------- */

  // The editor styles the element that is selected. With this on, the style goes to the classes of the element, so every
  // element with the same class changes with it.
  function byClass(ed, on) {
    ed.Selectors.setComponentFirst(!on);
    ed.trigger('component:toggled');
  }
  editor.Commands.add('hyper-class-mode', { run: function (ed) { byClass(ed, true); }, stop: function (ed) { byClass(ed, false); } });
  editor.Panels.addButton('options', { id: 'hyper-class-mode', className: 'fa fa-tags', command: 'hyper-class-mode', attributes: { title: L.classMode } });

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
