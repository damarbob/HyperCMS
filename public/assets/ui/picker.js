/*
 * The search picker of the entry form. A `select[data-picker]` holds only the entries that are chosen, and this script
 * puts a search box beside it. The box asks `data-picker` for entries by label, and a chosen entry becomes an option of
 * the select, so the select is still what the form posts and the form works the same without this script.
 *
 * It follows the combobox pattern: the box has `role="combobox"`, the results are a listbox, the arrow keys move
 * through them, Enter chooses and Escape closes.
 */
(function () {
  'use strict';

  var uid = 0;

  function meta(name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute('content') : '';
  }

  function build(select) {
    uid += 1;
    var many = select.multiple;
    var url = select.getAttribute('data-picker');
    var listId = 'picker-list-' + uid;

    var root = document.createElement('div');
    root.className = 'picker';

    var chips = document.createElement('div');
    chips.className = 'chips picker-chips';

    var input = document.createElement('input');
    input.type = 'search';
    input.className = 'input';
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);
    input.placeholder = select.getAttribute('data-placeholder') || '';
    if (select.getAttribute('aria-describedby')) input.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));

    var list = document.createElement('ul');
    list.className = 'menu picker-list';
    list.id = listId;
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    var status = document.createElement('p');
    status.className = 'sr-only';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');

    root.appendChild(chips);
    root.appendChild(input);
    root.appendChild(list);
    root.appendChild(status);
    select.hidden = true;
    select.parentNode.insertBefore(root, select);

    // The label of a field points at the select, which is hidden now, so it points at the box instead.
    var id = select.id;
    if (id) {
      select.id = id + '-select';
      input.id = id;
    }

    var items = [];
    var active = -1;
    var timer = null;
    var request = 0;

    function chosen() {
      return Array.prototype.filter.call(select.options, function (o) { return o.selected && o.value !== ''; });
    }

    function drawChips() {
      chips.textContent = '';
      chosen().forEach(function (option) {
        var chip = document.createElement('span');
        chip.className = 'chip';
        chip.appendChild(document.createTextNode(option.textContent));
        if (many || !select.required) {
          var x = document.createElement('button');
          x.type = 'button';
          x.className = 'x';
          x.textContent = '×';
          x.setAttribute('aria-label', (select.getAttribute('data-remove') || '').replace('{name}', option.textContent));
          x.addEventListener('click', function () {
            option.selected = false;
            if (!many) option.remove();
            drawChips();
            changed();
            input.focus();
          });
          chip.appendChild(x);
        }
        chips.appendChild(chip);
      });
      // A single choice is its chip, so the box is only needed to change it.
      input.hidden = !many && chosen().length > 0;
    }

    function changed() {
      select.dispatchEvent(new Event('change', { bubbles: true }));
      select.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function choose(item) {
      var option = Array.prototype.filter.call(select.options, function (o) { return o.value === String(item.id); })[0];
      if (!option) {
        option = document.createElement('option');
        option.value = String(item.id);
        option.textContent = item.label;
        select.appendChild(option);
      }
      if (!many) {
        Array.prototype.forEach.call(select.options, function (o) { o.selected = false; });
      }
      option.selected = true;
      drawChips();
      close();
      input.value = '';
      changed();
      if (many) input.focus();
    }

    function close() {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
    }

    function setActive(n) {
      var nodes = list.querySelectorAll('[role="option"]');
      if (nodes.length === 0) return;
      active = (n + nodes.length) % nodes.length;
      nodes.forEach(function (node, i) {
        node.setAttribute('aria-selected', i === active ? 'true' : 'false');
        node.classList.toggle('active', i === active);
      });
      input.setAttribute('aria-activedescendant', nodes[active].id);
      nodes[active].scrollIntoView({ block: 'nearest' });
    }

    function draw(answer) {
      list.textContent = '';
      var taken = chosen().map(function (o) { return o.value; });
      items = answer.items.filter(function (item) { return taken.indexOf(String(item.id)) < 0; });

      items.forEach(function (item, i) {
        var li = document.createElement('li');
        li.setAttribute('role', 'option');
        li.id = listId + '-' + i;
        li.setAttribute('aria-selected', 'false');
        li.textContent = item.label;
        li.addEventListener('mousedown', function (event) { event.preventDefault(); choose(item); });
        list.appendChild(li);
      });

      if (items.length === 0) {
        var none = document.createElement('li');
        none.className = 'muted none';
        none.textContent = select.getAttribute('data-none') || '';
        list.appendChild(none);
      } else if (answer.more) {
        var more = document.createElement('li');
        more.className = 'muted none';
        more.textContent = select.getAttribute('data-more') || '';
        list.appendChild(more);
      }

      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      status.textContent = items.length === 0 ? (select.getAttribute('data-none') || '') : String(items.length);
      active = -1;
    }

    function search() {
      var mine = ++request;
      fetch(url + (url.indexOf('?') < 0 ? '?' : '&') + 'q=' + encodeURIComponent(input.value), {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', [meta('csrf-header')]: meta('csrf-token') },
      }).then(function (response) { return response.json(); }).then(function (answer) {
        if (mine === request) draw(answer);
      }).catch(function () {
        if (mine === request) close();
      });
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(search, 250);
    });
    input.addEventListener('focus', function () { if (list.hidden) search(); });
    input.addEventListener('blur', function () { setTimeout(close, 120); });
    input.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (list.hidden) search();
        else setActive(active + 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(active - 1);
      } else if (event.key === 'Enter') {
        if (!list.hidden && active >= 0 && items[active]) {
          event.preventDefault();
          choose(items[active]);
        } else if (!list.hidden) {
          // Enter in the box must not send the form while the results are open.
          event.preventDefault();
        }
      } else if (event.key === 'Escape' && !list.hidden) {
        event.preventDefault();
        close();
      }
    });

    drawChips();
  }

  function setUp(root) {
    root.querySelectorAll('select[data-picker]').forEach(function (select) {
      if (select.dataset.pickerReady) return;
      select.dataset.pickerReady = '1';
      build(select);
    });
  }

  document.addEventListener('DOMContentLoaded', function () { setUp(document); });
  document.addEventListener('htmx:afterSwap', function (event) { setUp(event.target); });
})();
