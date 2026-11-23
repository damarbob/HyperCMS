/*
 * The comparison table in the browser. `comparison` is the Alpine component of the screen `admin/data-comparison`.
 *
 * - A column is an entry, from this site or from a data source of the person. A row is a field: a field of the entry's model,
 *   a custom field whose cells the person types, or a formula over other cells (see `comparison-core.js`).
 * - The layout is kept as a "state", which a person saves under a name. The shape is the one earlier versions saved, so the
 *   states that exist keep working: `{headers, fields, cells, timestamp}`.
 * - A cell of a field of this site is written back to the entry when it is edited. The server checks that the person may.
 *
 * What the server gives the screen is in the JSON block `#comparison-data`.
 */
(function () {
  'use strict';

  var core = window.ComparisonCore;

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

  var sequence = 0;
  function newId() { return 'd' + Date.now().toString(36) + (++sequence); }

  function show(value) {
    if (value === null || value === undefined) return '';
    return typeof value === 'object' ? JSON.stringify(value) : String(value);
  }

  document.addEventListener('alpine:init', function () {
    Alpine.data('comparison', function () {
      var cfg = JSON.parse(document.getElementById('comparison-data').textContent);

      return {
        L: cfg.labels,
        models: cfg.models,
        sources: cfg.sources,
        states: cfg.states || {},
        stateName: '',
        baseline: '',

        columns: [],
        rows: [],
        custom: {},
        values: {},
        loading: {},

        undoStack: [],
        redoStack: [],
        editing: null,
        drag: null,

        entryForm: { mode: 'add', index: -1, sourceId: '', modelId: '', entryId: '', entries: [], label: '', pickFields: [], fieldIds: [], preview: null, busy: false },
        fieldForm: { mode: 'add', index: -1, type: 'field', modelId: '', fieldId: '', label: '', customId: '', formula: '' },
        nameForm: { name: '' },

        /* ---- Start ---------------------------------------------------------------------------------------- */

        init: function () {
          this.baseline = this.signature();
          var self = this;
          window.addEventListener('beforeunload', function (event) {
            if (self.dirty) { event.preventDefault(); event.returnValue = ''; }
          });
          if (cfg.last && this.states[cfg.last]) this.apply(this.states[cfg.last], cfg.last);
        },

        get dirty() { return this.signature() !== this.baseline; },
        get stateNames() { return Object.keys(this.states); },
        get canUndo() { return this.undoStack.length > 0; },
        get canRedo() { return this.redoStack.length > 0; },

        /* ---- The state: what is saved, undone and compared -------------------------------------------------- */

        snapshot: function () {
          var self = this;
          var cells = [];
          this.rows.forEach(function (row) {
            if (row.fieldType !== 'custom-field') return;
            self.columns.forEach(function (col) {
              cells.push({ fieldId: row.fieldId, entryId: col.id, value: self.custom[col.id + '|' + row.fieldId] || '' });
            });
          });
          return {
            headers: this.columns.map(function (c) { return { id: String(c.id), source: String(c.source), modelId: String(c.modelId), entryId: String(c.entryId), label: c.label }; }),
            fields: this.rows.map(function (r) {
              var field = { fieldType: r.fieldType, fieldId: r.fieldId, title: r.title };
              if (r.additionalAttrs && Object.keys(r.additionalAttrs).length) field.additionalAttrs = r.additionalAttrs;
              return field;
            }),
            cells: cells,
            timestamp: new Date().toISOString(),
          };
        },

        // What a change would be compared with, without the time, which is always different.
        signature: function () {
          var state = this.snapshot();
          delete state.timestamp;
          return JSON.stringify(state);
        },

        apply: function (state, name) {
          var self = this;
          this.columns = (state.headers || []).map(function (h) { return { id: String(h.id), source: String(h.source), modelId: String(h.modelId), entryId: String(h.entryId), label: h.label || '' }; });
          this.rows = (state.fields || []).map(function (f) { return { fieldType: f.fieldType, fieldId: f.fieldId, title: f.title || f.fieldId, additionalAttrs: f.additionalAttrs || {} }; });
          this.custom = {};
          (state.cells || []).forEach(function (cell) { self.custom[cell.entryId + '|' + cell.fieldId] = cell.value; });
          this.values = {};
          if (name !== undefined) { this.stateName = name; this.baseline = this.signature(); }
          this.fetchAll();
        },

        remember: function () {
          this.undoStack.push(this.snapshot());
          this.redoStack = [];
        },

        undo: function () {
          if (!this.undoStack.length) return;
          this.redoStack.push(this.snapshot());
          this.apply(this.undoStack.pop());
        },

        redo: function () {
          if (!this.redoStack.length) return;
          this.undoStack.push(this.snapshot());
          this.apply(this.redoStack.pop());
        },

        /* ---- Saved states --------------------------------------------------------------------------------- */

        post: function (url, fields) {
          var body = new FormData();
          Object.keys(fields).forEach(function (key) { body.append(key, fields[key]); });
          body.append(cfg.csrf.name, cfg.csrf.hash);
          var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
          headers[meta('csrf-header')] = meta('csrf-token');
          return fetch(url, { method: 'POST', headers: headers, body: body, credentials: 'same-origin' }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (answer) {
              if (!response.ok) throw new Error(answer.message || answer.error || String(response.status));
              return answer;
            });
          });
        },

        keepStates: function () {
          var self = this;
          return this.post(cfg.urls.states, { states: JSON.stringify(this.states) }).catch(function (error) {
            toast('bad', self.L.failedToSaveStates + ' ' + error.message);
            throw error;
          });
        },

        keepLast: function () { this.post(cfg.urls.last, { name: this.stateName }).catch(function () { /* a lost note of the last state is not worth a message */ }); },

        saveState: function () {
          if (!this.stateName) { this.openName(); return; }
          this.writeState(this.stateName);
        },

        writeState: function (name) {
          var self = this;
          this.states[name] = this.snapshot();
          this.stateName = name;
          this.keepStates().then(function () {
            self.baseline = self.signature();
            self.keepLast();
            toast('ok', fill(self.L.statexSavedSuccessfully, { x: name }));
          });
        },

        openName: function () {
          this.nameForm.name = this.stateName;
          document.getElementById('cmp-name').showModal();
          var self = this;
          this.$nextTick(function () { var input = self.$refs.stateName; if (input) input.focus(); });
        },

        saveAs: function () {
          var name = this.nameForm.name.trim();
          if (!name) return;
          var self = this;
          var go = function () { document.getElementById('cmp-name').close(); self.writeState(name); };
          if (this.states[name] && name !== this.stateName) {
            window.HyperUi.confirm(fill(this.L.statexAlreadyExistsOverwrite, { x: name })).then(function (yes) { if (yes) go(); });
          } else {
            go();
          }
        },

        chooseState: function (name) {
          var self = this;
          if (!name) { return; }
          var load = function () { self.undoStack = []; self.redoStack = []; self.apply(self.states[name], name); self.keepLast(); };
          if (this.dirty) {
            window.HyperUi.confirm(this.L.youHaveUnsavedChangesLoadAnyway).then(function (yes) { if (yes) load(); });
          } else {
            load();
          }
        },

        deleteState: function () {
          var name = this.stateName;
          if (!name || !this.states[name]) return;
          var self = this;
          window.HyperUi.confirm(fill(this.L.deleteStatex, { x: name })).then(function (yes) {
            if (!yes) return;
            delete self.states[name];
            self.stateName = '';
            self.keepStates().then(function () { self.keepLast(); toast('ok', fill(self.L.statexDeletedSuccessfully, { x: name })); });
          });
        },

        /* ---- The data of the columns ----------------------------------------------------------------------- */

        source: function (id) { return this.sources.filter(function (s) { return String(s.id) === String(id); })[0] || null; },
        model: function (id) { return this.models.filter(function (m) { return String(m.id) === String(id); })[0] || null; },

        fetchAll: function () { var self = this; this.columns.forEach(function (col) { self.fetchColumn(col); }); },

        // Ask the source of a column for the fields of its entry. Answers with the fields, or fails.
        request: function (col) {
          var source = this.source(col.source);
          if (!source) return Promise.reject(new Error('source'));
          var options = source.options || {};
          var model = this.model(col.modelId);

          var pairs = core.flatten({ id: col.id, entry_id: col.entryId, model_id: col.modelId, model: model ? { name: model.name, fields: model.fields } : null }, '', []);
          var body = new FormData();
          pairs.forEach(function (pair) { body.append(pair[0], pair[1]); });
          var headers = Object.assign({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, options.headers || {});
          // The token of the page goes only to this site's own source, never to an address that a person typed.
          if (source.internal) { body.append(cfg.csrf.name, cfg.csrf.hash); headers[meta('csrf-header')] = meta('csrf-token'); }

          return fetch(options.url, { method: String(options.type || 'POST').toUpperCase(), headers: headers, body: body, credentials: 'same-origin' })
            .then(function (response) { if (!response.ok) throw new Error(String(response.status)); return response.json(); })
            .then(function (answer) {
              var list = Array.isArray(answer.data) ? answer.data : [];
              var row = list.filter(function (r) { return String(r.id) === String(col.entryId); })[0] || list[0];
              return row && row.fields && typeof row.fields === 'object' ? row.fields : {};
            });
        },

        fetchColumn: function (col) {
          var self = this;
          this.loading[col.id] = true;
          this.request(col)
            .then(function (fields) { self.values[col.id] = fields; })
            .catch(function () { toast('bad', self.L.failedToFetchEntryData + ' ' + col.id); })
            .then(function () { self.loading[col.id] = false; });
        },

        /* ---- Cells ------------------------------------------------------------------------------------------ */

        // What the cell holds before a formula looks at it.
        raw: function (row, col, seen) {
          if (row.fieldType === 'custom-field') return this.custom[col.id + '|' + row.fieldId] || '';
          if (row.fieldType === 'math-formula') {
            var value = this.formula(row, col, seen);
            return value === null ? '' : String(value);
          }
          return show((this.values[col.id] || {})[row.fieldId]);
        },

        text: function (row, col) {
          if (row.fieldType === 'math-formula') {
            var value = this.formula(row, col);
            return value === null ? this.L.formulaError : String(value);
          }
          return this.raw(row, col);
        },

        formula: function (row, col, seen) {
          var self = this;
          seen = seen || {};
          var key = col.id + '|' + row.fieldId;
          if (seen[key] || !window.math) return null;
          var next = Object.assign({}, seen);
          next[key] = true;
          var read = function (ref) {
            var target = ref.dataId
              ? self.columns.filter(function (c) { return c.id === ref.dataId; })[0]
              : self.columns.filter(function (c) { return String(c.entryId) === String(ref.entryId) && (!ref.modelId || String(c.modelId) === String(ref.modelId)); })[0];
            var field = self.rows.filter(function (r) { return r.fieldId === ref.fieldId; })[0];
            return target && field ? self.raw(field, target, next) : null;
          };
          return core.evaluate(window.math, (row.additionalAttrs || {})['data-formula'] || '', col.entryId, read);
        },

        // A cell can be typed in when its row is the person's own or when its column is an entry of this site.
        editable: function (row, col) {
          if (row.fieldType === 'math-formula') return false;
          if (row.fieldType === 'custom-field') return true;
          var source = this.source(col.source);
          return !!(source && source.internal);
        },

        startEdit: function (row, col) {
          if (row.fieldType === 'math-formula') return;
          if (!this.editable(row, col)) { toast('warn', this.L.entryFromExternalDataSource); return; }
          this.editing = { col: col.id, row: row.fieldId, text: this.raw(row, col) };
          var self = this;
          this.$nextTick(function () { var input = self.$root.querySelector('[data-cell-input]'); if (input) { input.focus(); input.select(); } });
        },

        isEditing: function (row, col) { return !!this.editing && this.editing.col === col.id && this.editing.row === row.fieldId; },

        cancelEdit: function () { this.editing = null; },

        commitEdit: function (row, col) {
          if (!this.editing) return;
          var text = this.editing.text.trim();
          var before = this.raw(row, col);
          this.editing = null;
          if (text === before) return;

          if (row.fieldType === 'custom-field') {
            this.remember();
            this.custom[col.id + '|' + row.fieldId] = text;
            return;
          }
          var self = this;
          this.post(cfg.urls.update, { entry_id: col.entryId, field_id: row.fieldId, value: text })
            .then(function (answer) {
              var all = Object.assign({}, self.values[col.id] || {});
              all[row.fieldId] = answer.value !== undefined ? answer.value : text;
              self.values[col.id] = all;
              toast('ok', answer.message || self.L.entryUpdatedSuccessfully);
            })
            .catch(function (error) { toast('bad', error.message); });
        },

        /* ---- Moving columns and rows ------------------------------------------------------------------------ */

        startDrag: function (kind, index, event) {
          this.drag = { kind: kind, index: index };
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', kind + ':' + index);
        },

        over: function (kind, event) { if (this.drag && this.drag.kind === kind) event.preventDefault(); },

        drop: function (kind, index) {
          if (!this.drag || this.drag.kind !== kind) return;
          this.remember();
          if (kind === 'col') this.columns = core.move(this.columns, this.drag.index, index);
          else this.rows = core.move(this.rows, this.drag.index, index);
          this.drag = null;
        },

        endDrag: function () { this.drag = null; },

        /* ---- The dialog of a column ------------------------------------------------------------------------- */

        openEntry: function (index) {
          var col = index >= 0 ? this.columns[index] : null;
          var first = this.sources[0];
          this.entryForm = {
            mode: col ? 'edit' : 'add',
            index: index,
            sourceId: col ? col.source : (first ? String(first.id) : ''),
            modelId: col ? col.modelId : '',
            entryId: col ? col.entryId : '',
            entries: [],
            label: col ? col.label : '',
            pickFields: [],
            fieldIds: [],
            preview: null,
            busy: false,
          };
          if (col) this.loadEntries(); else this.pickModel();
          document.getElementById('cmp-entry').showModal();
        },

        get entrySource() { return this.source(this.entryForm.sourceId); },
        get entryModel() { return this.model(this.entryForm.modelId); },

        // The entries of a model can be listed for this site. For another source the number of the entry is typed.
        loadEntries: function () {
          var form = this.entryForm;
          form.entries = [];
          var source = this.source(form.sourceId);
          if (!form.modelId || !source || !source.internal) return;
          var self = this;
          this.post(cfg.urls.entries, { model_id: form.modelId })
            .then(function (answer) { form.entries = answer.data || []; })
            .catch(function () { toast('bad', self.L.failedToFetchEntries); });
        },

        // The fields of the chosen model are offered as rows, except the ones the table already has.
        pickModel: function () {
          var form = this.entryForm;
          var model = this.model(form.modelId);
          var have = this.rows.map(function (r) { return r.fieldId; });
          form.pickFields = model ? model.fields.filter(function (f) { return have.indexOf(f.id) === -1; }) : [];
          form.fieldIds = form.pickFields.map(function (f) { return f.id; });
          form.entryId = '';
          form.preview = null;
          this.loadEntries();
        },

        previewEntry: function () {
          var form = this.entryForm;
          if (!form.entryId) return;
          var self = this;
          form.busy = true;
          this.request({ id: 'preview', source: form.sourceId, modelId: form.modelId, entryId: form.entryId })
            .then(function (fields) { form.preview = Object.keys(fields).map(function (key) { return { key: key, value: show(fields[key]) }; }); })
            .catch(function () { toast('bad', self.L.failedToFetchEntryData); })
            .then(function () { form.busy = false; });
        },

        saveEntry: function () {
          var form = this.entryForm;
          if (!form.sourceId || !form.entryId) { toast('warn', this.L.pleaseFillAllRequiredFields); return; }
          this.remember();
          var model = this.model(form.modelId);
          var entryName = form.label.trim() || (model ? model.name + ' ' : '') + '#' + form.entryId;
          var column = { id: form.mode === 'edit' ? this.columns[form.index].id : newId(), source: String(form.sourceId), modelId: String(form.modelId), entryId: String(form.entryId), label: entryName };

          if (form.mode === 'edit') {
            this.columns.splice(form.index, 1, column);
            delete this.values[column.id];
          } else {
            this.columns.push(column);
            var self = this;
            var have = this.rows.map(function (r) { return r.fieldId; });
            (model ? model.fields : []).forEach(function (f) {
              if (form.fieldIds.indexOf(f.id) !== -1 && have.indexOf(f.id) === -1) self.rows.push({ fieldType: 'field', fieldId: f.id, title: f.label, additionalAttrs: {} });
            });
          }
          document.getElementById('cmp-entry').close();
          this.fetchColumn(column);
        },

        removeColumn: function (index) {
          this.remember();
          var gone = this.columns.splice(index, 1)[0];
          delete this.values[gone.id];
        },

        /* ---- The dialog of a row --------------------------------------------------------------------------- */

        openField: function (index) {
          var row = index >= 0 ? this.rows[index] : null;
          var f = { mode: row ? 'edit' : 'add', index: index, type: row ? row.fieldType : 'field', modelId: '', fieldId: '', label: row ? row.title : '', customId: '', formula: '' };
          if (row && row.fieldType === 'custom-field') f.customId = row.fieldId;
          if (row && row.fieldType === 'math-formula') f.formula = (row.additionalAttrs || {})['data-formula'] || '';
          if (row && row.fieldType === 'field') {
            f.fieldId = row.fieldId;
            var owner = this.models.filter(function (m) { return m.fields.some(function (x) { return x.id === row.fieldId; }); })[0];
            f.modelId = owner ? String(owner.id) : '';
          }
          this.fieldForm = f;
          document.getElementById('cmp-field').showModal();
        },

        get fieldModel() { return this.model(this.fieldForm.modelId); },

        saveField: function () {
          var f = this.fieldForm;
          var row;
          if (f.type === 'field') {
            if (!f.fieldId) { toast('warn', this.L.pleaseSelectField); return; }
            var def = (this.fieldModel ? this.fieldModel.fields : []).filter(function (x) { return x.id === f.fieldId; })[0];
            row = { fieldType: 'field', fieldId: f.fieldId, title: def ? def.label : f.fieldId, additionalAttrs: {} };
          } else if (f.type === 'custom-field') {
            var id = f.customId.trim();
            if (!id) { toast('warn', this.L.pleaseProvideBothFieldLabelAndId); return; }
            row = { fieldType: 'custom-field', fieldId: id, title: f.label.trim() || id, additionalAttrs: {} };
          } else {
            var formula = f.formula.trim();
            if (!formula) { toast('warn', this.L.pleaseProvideFormula); return; }
            var keep = f.mode === 'edit' ? this.rows[f.index].fieldId : 'math-formula-' + newId();
            row = { fieldType: 'math-formula', fieldId: keep, title: f.label.trim() || formula, additionalAttrs: { 'data-formula': formula } };
          }

          var clash = this.rows.some(function (r, i) { return r.fieldId === row.fieldId && i !== f.index; });
          if (clash) { toast('warn', fill(this.L.fieldxAlreadyExists, { x: row.fieldId })); return; }

          this.remember();
          if (f.mode === 'edit') {
            // A change of the id of a custom field takes its cells along.
            var old = this.rows[f.index];
            if (old.fieldId !== row.fieldId) {
              var self = this;
              this.columns.forEach(function (c) {
                var from = c.id + '|' + old.fieldId;
                if (from in self.custom) { self.custom[c.id + '|' + row.fieldId] = self.custom[from]; delete self.custom[from]; }
              });
            }
            this.rows.splice(f.index, 1, row);
          } else {
            this.rows.push(row);
          }
          document.getElementById('cmp-field').close();
        },

        removeRow: function (index) {
          this.remember();
          this.rows.splice(index, 1);
        },
      };
    });
  });
})();
