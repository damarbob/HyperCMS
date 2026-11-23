/*
 * The parts of the comparison table that need no page: reading the references of a formula, working out its value, and moving
 * an item of a list. They are kept apart so they can be tried without a browser.
 *
 * A formula is an expression for math.js with references to cells in square brackets:
 *   [price]            the field `price` of the entry of the same column
 *   [e12:price]        the field of the entry with the number 12
 *   [m3:price]         the field of an entry of the model 3 (the first column of that model)
 *   [m3:e12:price]     both
 *   [d5:price]         the column that has the id d5, whatever its entry
 * A reference that finds no cell, or a cell that is not a number, counts as 0.
 */
(function (root) {
  'use strict';

  // What a formula may not hold. The text a reference puts in is always a number, so it cannot carry any of this.
  var BLOCKED = /[;={}"'`\[\]]|\b(import|createUnit|evaluate|parse|compile|simplify|derivative|resolve|reviver|function)\b/i;

  /**
   * Read the text between the brackets of a reference.
   *
   * @param {string} token    for example `m3:e12:price`
   * @param {string} entryId  the entry of the column the formula is in, which a reference without one means
   */
  function parseToken(token, entryId) {
    var parts = String(token).split(':');
    var ref = { modelId: null, entryId: entryId, dataId: null, fieldId: '' };
    if (parts.length === 3) {
      ref.modelId = parts[0].replace(/^m/i, '');
      ref.entryId = parts[1].replace(/^e/i, '');
      ref.fieldId = parts[2];
    } else if (parts.length === 2) {
      ref.fieldId = parts[1];
      if (/^e\d+$/i.test(parts[0])) ref.entryId = parts[0].replace(/^e/i, '');
      else if (/^m\d+$/i.test(parts[0])) ref.modelId = parts[0].replace(/^m/i, '');
      else ref.dataId = parts[0];
    } else {
      ref.fieldId = parts[0];
    }
    return ref;
  }

  /**
   * Put the value of each reference into the formula.
   *
   * @param {string}   formula
   * @param {string}   entryId  the entry of the column the formula is in
   * @param {function} read     takes a reference and gives the text of its cell, or null
   */
  function inject(formula, entryId, read) {
    return String(formula).replace(/\[([^\[\]]+)\]/g, function (all, token) {
      var text = read(parseToken(token, entryId));
      var number = parseFloat(String(text === null || text === undefined ? '' : text).trim());
      return '(' + (isNaN(number) ? 0 : number) + ')';
    });
  }

  /**
   * The value of a formula, or null when it cannot be worked out (a blocked word, a syntax error, or a result that is not a
   * finite number).
   *
   * @param {object} math  math.js, or anything with `evaluate(expression)`
   */
  function evaluate(math, formula, entryId, read) {
    var expression = inject(formula, entryId, read);
    if (BLOCKED.test(expression)) return null;
    try {
      var result = math.evaluate(expression);
      // Twelve digits are enough for a person, and hide the 0.30000000000000004 of 0.1 + 0.2.
      return typeof result === 'number' && isFinite(result) ? +result.toPrecision(12) : null;
    } catch (e) {
      return null;
    }
  }

  /** A copy of the list with the item at `from` moved to `to`. */
  function move(list, from, to) {
    var copy = list.slice();
    if (from < 0 || from >= copy.length || to < 0 || to >= copy.length || from === to) return copy;
    copy.splice(to, 0, copy.splice(from, 1)[0]);
    return copy;
  }

  /** Form fields in the style jQuery used, `model[fields][0][id]`, so a source that read the old requests still can. */
  function flatten(value, key, out) {
    if (value !== null && typeof value === 'object') {
      Object.keys(value).forEach(function (name) { flatten(value[name], key === '' ? name : key + '[' + name + ']', out); });
    } else if (key !== '') {
      out.push([key, value === null || value === undefined ? '' : String(value)]);
    }
    return out;
  }

  var core = { parseToken: parseToken, inject: inject, evaluate: evaluate, move: move, flatten: flatten };
  root.ComparisonCore = core;
  if (typeof module !== 'undefined' && module.exports) module.exports = core;
})(typeof window !== 'undefined' ? window : globalThis);
