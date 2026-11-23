/*
 * The model builder. One Alpine component holds the definition of a model while it is edited, as a list of fields, and
 * writes it into the form when the form is sent. The same definition can be edited as JSON in Monaco, which loads the
 * first time that tab is opened.
 *
 * The server owns the rules. This script offers the options each type declares (`window` has nothing, the page carries
 * them in the `builder-data` block), and the server checks the whole definition again before it saves.
 */
(function () {
  'use strict';

  var SHARED = ['id', 'label', 'type', 'required', 'help', 'default', 'filterable', 'list'];
  var MONACO = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/+esm';

  function meta(name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute('content') : '';
  }

  function dark() {
    var theme = document.documentElement.getAttribute('data-theme');
    return theme === 'dark' || (theme === null && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }

  var counter = 0;
  function key() {
    counter += 1;
    return 'k' + counter;
  }

  function slug(text) {
    var id = String(text || '')
      .normalize('NFKD')
      .replace(/[̀-ͯ]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
    if (id === '') return '';
    if (!/^[a-z]/.test(id)) id = 'f_' + id;
    return id.slice(0, 64);
  }

  function blank(value) {
    return value === undefined || value === null || value === '';
  }

  document.addEventListener('alpine:init', function () {
    Alpine.data('modelBuilder', function () {
      return {
        catalog: { types: [], models: [], naming: [] },
        text: {},
        icons: [],
        fields: [],
        labelField: '',
        labelKey: null,
        saved: [],
        indexing: {},
        errors: {},
        urls: {},

        tab: 'fields',
        selectedKey: null,
        dirty: false,
        sending: false,
        submitted: '[]',
        announce: '',
        dragIndex: null,
        dropIndex: null,

        json: '',
        jsonProblems: [],
        jsonState: '',
        jsonBusy: false,
        jsonFallback: false,
        editor: null,

        init: function () {
          var node = document.getElementById('builder-data');
          var data = node ? JSON.parse(node.textContent) : {};

          this.catalog = data.catalog || this.catalog;
          this.text = data.text || {};
          this.icons = data.icons || [];
          this.labelField = data.labelField || '';
          this.saved = data.saved || [];
          this.indexing = data.indexing || {};
          this.errors = data.errors || {};
          this.urls = data.urls || {};
          this.fields = (data.fields || []).map(this.fromDefinition.bind(this));
          this.syncLabel();

          // After a failed save the first field with a problem is the one to look at.
          var bad = this.fields.filter(this.hasError.bind(this))[0];
          this.selectedKey = (bad || this.fields[0] || {})._key || null;
          this.submitted = JSON.stringify(this.definitions());
          // The page that comes back with errors holds changes that were not saved.
          // A new model has nothing to compare with, so its page offers Create at once.
          this.dirty = !!data.isNew || Object.keys(this.errors).length > 0 || document.querySelector('.problem-list') !== null;

          var self = this;
          this.$watch('fields', function () { self.syncLabel(); }, { deep: true });
          this.$watch('labelField', function () { self.syncLabel(); });
          window.addEventListener('beforeunload', function (event) {
            if (self.dirty && !self.sending) {
              event.preventDefault();
              event.returnValue = '';
            }
          });
        },

        /* The list ---------------------------------------------------------------------------------------------- */

        get current() {
          var self = this;
          return this.fields.filter(function (f) { return f._key === self.selectedKey; })[0] || null;
        },

        typeOf: function (name) {
          return this.catalog.types.filter(function (t) { return t.name === name; })[0] || null;
        },

        typeLabel: function (f) {
          var type = this.typeOf(f.type);
          var label = type ? type.label : f.type;
          if (f.type === 'choice' && f.multiple) return label + ', ' + this.text.optionMany;
          return label;
        },

        select: function (f) {
          this.selectedKey = f._key;
        },

        settingsOf: function (f) {
          var type = this.typeOf(f.type);
          return type ? type.settings : [];
        },

        addField: function (typeName) {
          var type = this.typeOf(typeName);
          if (!type) return;

          var f = this.fromDefinition({ id: '', label: '', type: typeName });
          f._idTouched = false;
          this.applyDefaults(f, type);
          this.fields.push(f);
          this.selectedKey = f._key;
          this.tab = 'fields';
          this.say(this.text.added.replace('{name}', type.label));

          var self = this;
          this.$nextTick(function () {
            var box = document.getElementById('f-label');
            if (box) box.focus();
            self.dirty = true;
          });
        },

        remove: function (f) {
          var i = this.fields.indexOf(f);
          if (i < 0) return;
          this.fields.splice(i, 1);
          var next = this.fields[Math.min(i, this.fields.length - 1)];
          this.selectedKey = next ? next._key : null;
          this.say(this.text.removed.replace('{name}', f.label || f.id));
          this.dirty = true;
        },

        move: function (i, by) {
          var to = i + by;
          if (to < 0 || to >= this.fields.length) return;
          var f = this.fields.splice(i, 1)[0];
          this.fields.splice(to, 0, f);
          this.selectedKey = f._key;
          this.dirty = true;
          this.say(this.text.movedTo.replace('{name}', f.label || f.id).replace('{n}', to + 1).replace('{total}', this.fields.length));
          var self = this;
          this.$nextTick(function () {
            var rows = self.$root.querySelectorAll('.field-row');
            var button = rows[to] && rows[to].querySelector('.move button:not([disabled])');
            if (button) button.focus();
          });
        },

        dragStart: function (i, event) {
          this.dragIndex = i;
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', String(i));
        },

        dropOn: function (i) {
          if (this.dragIndex === null || this.dragIndex === i) return;
          var f = this.fields.splice(this.dragIndex, 1)[0];
          this.fields.splice(i, 0, f);
          this.selectedKey = f._key;
          this.dragIndex = null;
          this.dropIndex = null;
          this.dirty = true;
        },

        say: function (message) {
          this.announce = message;
        },

        /* One field --------------------------------------------------------------------------------------------- */

        // A definition from the server becomes the object the form edits. Keys the builder has no control for are kept.
        fromDefinition: function (def) {
          var type = this.typeOf(def.type);
          var f = {
            _key: key(),
            _saved: this.saved.indexOf(def.id) >= 0 && def.id !== '',
            _idTouched: true,
            _extra: {},
            id: def.id || '',
            label: def.label || '',
            type: def.type || '',
            required: !!def.required,
            help: def.help || '',
            default: def.default === undefined || def.default === null ? '' : def.default,
            filterable: !!def.filterable,
            list: def.list === undefined ? (type ? type.listDefault : true) : !!def.list,
          };

          var own = {};
          var self = this;
          (type ? type.settings : []).forEach(function (s) {
            own[s.key] = true;
            var v = def[s.key];
            if (s.control === 'toggle') f[s.key] = v === undefined ? !!s.default : !!v;
            else if (s.control === 'choices') f[s.key] = Array.isArray(v) ? v.map(function (c) { return { value: String(c.value), label: String(c.label || '') }; }) : [];
            else f[s.key] = blank(v) ? (blank(s.default) ? '' : s.default) : (s.control === 'model' ? String(v) : v);
          });

          Object.keys(def).forEach(function (k) {
            if (SHARED.indexOf(k) < 0 && !own[k]) f._extra[k] = def[k];
          });

          return f;
        },

        applyDefaults: function (f, type) {
          type.settings.forEach(function (s) {
            if (s.control === 'toggle') f[s.key] = !!s.default;
            else if (s.control === 'choices') f[s.key] = [{ value: '', label: '' }];
            else f[s.key] = blank(s.default) ? '' : s.default;
          });
          f.list = type.listDefault;
          f.default = '';
          f.filterable = false;
        },

        changeType: function (f, name) {
          var type = this.typeOf(name);
          if (!type || name === f.type) return;
          var self = this;
          // The options of the old type mean nothing to the new one, so they go.
          this.settingsOf(f).forEach(function (s) { delete f[s.key]; });
          f.type = name;
          this.applyDefaults(f, type);
          if (f.id === this.labelField && !this.nameableField(f)) this.labelField = '';
          this.say(this.text.typeChanged.replace('{name}', f.label || f.id));
          this.dirty = true;
        },

        settingChanged: function (f, name) {
          // A choice of several cannot be filterable or name the entries.
          if (name === 'multiple' && f.multiple) {
            if (!this.canFilter(f)) f.filterable = false;
            if (f.type === 'choice' && f.widget === 'select') f.widget = 'checkboxes';
          }
        },

        labelTyped: function (f) {
          // The id follows the label until the person writes one, and never changes once the field is saved.
          if (f._saved || f._idTouched) return;
          var base = slug(f.label);
          var id = base;
          var n = 2;
          var self = this;
          while (id !== '' && this.fields.some(function (o) { return o !== f && o.id === id; })) {
            id = base + '_' + n;
            n += 1;
          }
          f.id = id;
        },

        defaultKind: function (f) {
          if (f.type === 'boolean') return 'boolean';
          if (f.type === 'choice') return f.multiple ? '' : 'choice';
          if (f.type === 'color') return 'color';
          if (f.type === 'number') return 'number';
          if (['text', 'longtext', 'hidden'].indexOf(f.type) >= 0) return 'text';
          return '';
        },

        choicesOf: function (f) {
          return (f.choices || []).filter(function (c) { return c.value !== ''; });
        },

        canFilter: function (f) {
          var type = this.typeOf(f.type);
          if (!type) return false;
          if (type.filterable === 'always') return true;
          return type.filterable === 'single' && !f.multiple;
        },

        filterHint: function (f) {
          var type = this.typeOf(f.type);
          if (!type || type.filterable === 'never') return this.text.filterableNever;
          if (!this.canFilter(f)) return this.text.filterableSingle;
          return this.text.filterableHelp;
        },

        indexState: function (f) {
          return this.indexing[f.id] || '';
        },

        indexLabel: function (f) {
          var state = f._saved ? this.indexState(f) : '';
          if (state === 'indexed') return this.text.stateIndexed;
          if (state === 'indexing') return this.text.stateIndexing;
          return this.text.stateOnSave;
        },

        // How many fields start indexing when this is saved: filterable now, and not already indexed or indexing.
        newIndexes: function () {
          var self = this;
          return this.fields.filter(function (f) { return f.filterable && self.canFilter(f) && !(f._saved && self.indexing[f.id]); }).length;
        },

        indexNote: function () {
          return this.text.indexNote.replace('{n}', this.newIndexes());
        },

        /* Naming the entries ------------------------------------------------------------------------------------ */

        nameableField: function (f) {
          if (this.catalog.naming.indexOf(f.type) < 0 || f.multiple) return false;
          if (f.type === 'text' && f.format === 'password') return false;
          return f.id !== '';
        },

        nameable: function () {
          var self = this;
          return this.fields.filter(function (f) { return self.nameableField(f); });
        },

        // Keeps the label field and the field it names together. A field whose id is edited keeps its place as the name
        // of the entries, and a choice made in the select points at the field with that id.
        syncLabel: function () {
          var self = this;
          if (this.labelField === '') {
            this.labelKey = null;
            return;
          }

          var picked = this.fields.filter(function (f) { return f.id === self.labelField; })[0];
          if (picked) {
            this.labelKey = picked._key;
            if (!this.nameableField(picked)) {
              this.labelField = '';
              this.labelKey = null;
            }
            return;
          }

          // No field has that id any more, so the field was renamed or removed.
          var named = this.fields.filter(function (f) { return f._key === self.labelKey; })[0];
          if (named && this.nameableField(named)) {
            this.labelField = named.id;
          } else {
            this.labelField = '';
            this.labelKey = null;
          }
        },

        /* The stored definition --------------------------------------------------------------------------------- */

        definition: function (f) {
          var type = this.typeOf(f.type);
          var def = { id: f.id, label: f.label, type: f.type };

          (type ? type.settings : []).forEach(function (s) {
            var v = f[s.key];
            if (s.control === 'toggle') def[s.key] = !!v;
            else if (s.control === 'select') {
              var chosen = blank(v) ? s.default : v;
              if (!(s.omitDefault && chosen === s.default)) def[s.key] = chosen;
            }
            else if (s.control === 'number') { if (!blank(v) && !isNaN(Number(v))) def[s.key] = Number(v); }
            else if (s.control === 'model') { if (!blank(v)) def[s.key] = Number(v); }
            else if (s.control === 'text') { if (!blank(v) && String(v).trim() !== '') def[s.key] = String(v).trim(); }
            else if (s.control === 'choices') {
              def[s.key] = (v || [])
                .filter(function (c) { return String(c.value).trim() !== '' || String(c.label).trim() !== ''; })
                .map(function (c) { return { value: String(c.value).trim(), label: String(c.label).trim() || String(c.value).trim() }; });
            }
          });

          if (f.required) def.required = true;
          if (!blank(f.help) && String(f.help).trim() !== '') def.help = String(f.help).trim();
          if (f.type === 'boolean') { if (f.default === true) def.default = true; }
          else if (f.type === 'number') { if (!blank(f.default) && !isNaN(Number(f.default))) def.default = Number(f.default); }
          else if (!blank(f.default) && !(Array.isArray(f.default) && f.default.length === 0)) def.default = f.default;
          if (f.filterable && this.canFilter(f)) def.filterable = true;
          if (type && f.list !== type.listDefault) def.list = !!f.list;

          Object.keys(f._extra || {}).forEach(function (k) { def[k] = f._extra[k]; });
          return def;
        },

        definitions: function () {
          var self = this;
          return this.fields.map(function (f) { return self.definition(f); });
        },

        /* Problems ---------------------------------------------------------------------------------------------- */

        errorsOf: function (f) {
          return this.errors[f.id] || [];
        },

        hasError: function (f) {
          return this.errorsOf(f).length > 0;
        },

        /* Tabs and the JSON editor ------------------------------------------------------------------------------ */

        show: function (name) {
          if (name === this.tab) return;
          var self = this;

          if (this.tab === 'json') {
            // Leaving the JSON editor reads it back, and a text that is not JSON keeps the person here.
            if (!this.readJson()) return;
          }
          this.tab = name;
          if (name === 'json') this.openJson();
        },

        openJson: function () {
          this.json = JSON.stringify(this.definitions(), null, 2);
          this.jsonProblems = [];
          this.jsonState = '';
          if (this.editor) {
            this.editor.setValue(this.json);
            return;
          }
          this.loadEditor();
        },

        loadEditor: function () {
          var self = this;
          this.jsonBusy = true;
          import(MONACO).then(function (monaco) {
            self.jsonBusy = false;
            monaco.editor.defineTheme('hyper-light', { base: 'vs', inherit: true, rules: [], colors: { 'editor.background': '#ffffff' } });
            monaco.editor.defineTheme('hyper-dark', { base: 'vs-dark', inherit: true, rules: [], colors: { 'editor.background': '#14161a' } });
            self.editor = monaco.editor.create(self.$refs.monaco, {
              value: self.json,
              language: 'json',
              theme: dark() ? 'hyper-dark' : 'hyper-light',
              automaticLayout: true,
              minimap: { enabled: false },
              scrollBeyondLastLine: false,
              tabSize: 2,
              fontFamily: 'Geist Mono, ui-monospace, monospace',
              fontSize: 13,
              ariaLabel: self.text.tabJson,
            });
            self.editor.onDidChangeModelContent(function () {
              self.json = self.editor.getValue();
              self.dirty = true;
              self.jsonState = '';
            });
          }).catch(function () {
            self.jsonBusy = false;
            self.jsonFallback = true;
          });
        },

        // Read the JSON text into the list. Returns false, and says why, when it is not JSON.
        readJson: function () {
          var data;
          try {
            data = JSON.parse(this.json);
          } catch (e) {
            this.jsonProblems = [this.text.jsonInvalid.replace('{message}', e.message)];
            this.jsonState = 'bad';
            return false;
          }
          if (!Array.isArray(data)) {
            this.jsonProblems = [this.text.jsonNotList];
            this.jsonState = 'bad';
            return false;
          }

          var before = JSON.stringify(this.definitions());
          if (JSON.stringify(data) !== before) {
            var self = this;
            var keep = this.selectedKey;
            this.fields = data.filter(function (d) { return d && typeof d === 'object' && !Array.isArray(d); }).map(this.fromDefinition.bind(this));
            this.selectedKey = (this.fields[0] || {})._key || null;
            this.dirty = true;
          }
          this.jsonProblems = [];

          return true;
        },

        // Ask the server to check the text, which reports each problem with the field it belongs to.
        checkJson: function () {
          var self = this;
          this.jsonProblems = [];
          this.jsonState = '';

          var body = new URLSearchParams();
          body.set('fields', this.json);
          body.set('label_field', this.labelField);
          body.set('hyper_csrf_token', meta('csrf-token'));

          fetch(this.urls.check, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', [meta('csrf-header')]: meta('csrf-token') },
            body: body,
            credentials: 'same-origin',
          }).then(function (r) { return r.json(); }).then(function (answer) {
            if (answer.ok) {
              self.jsonState = 'ok';
              return;
            }
            self.jsonState = 'bad';
            self.jsonProblems = answer.errors.map(function (e) { return e.message; });
          }).catch(function () {
            self.jsonProblems = [self.text.jsonFailedCheck];
          });
        },

        /* Sending ----------------------------------------------------------------------------------------------- */

        beforeSubmit: function (event) {
          // On the JSON tab the text is the definition. Anywhere else the list is.
          if (this.tab === 'json') {
            if (!this.readJson()) {
              event.preventDefault();
              return;
            }
          }
          this.submitted = JSON.stringify(this.definitions());
          var input = this.$root.querySelector('input[name="fields"]');
          if (input) input.value = this.submitted;
          this.sending = true;
        },
      };
    });
  });
})();
