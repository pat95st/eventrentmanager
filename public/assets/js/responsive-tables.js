// Responsive tables, modelled on the DataTables "Responsive" extension.
//
// Usage: add data-responsive to a <table> with <thead>/<tbody>. Columns that
// do not fit into the available width are hidden, starting with the least
// important one. Rows with hidden columns get a (+) button in their first
// cell; it expands a detail row that lists the hidden columns as
// "Header: value" (cell contents are cloned, so links and buttons keep
// working). Columns are re-shown as soon as there is room again. A column
// counts as fitting only if all visible cells fit without wrapping.
//
// Column importance via data-priority on the <th> (like DataTables): lower
// numbers are more important and hidden last; columns without data-priority
// are hidden first, from right to left. The first column is never hidden
// because it carries the toggle. data-priority="0" keeps a column visible
// at all times.
//
// Works together with row-links.js (the toggle is a <button>, so it does not
// open the row link) and sortable-tables.js (open detail rows are taken out
// before sorting and re-attached to their rows afterwards).
(function () {
  'use strict';

  var SELECTOR = 'table[data-responsive]';

  var style = document.createElement('style');
  style.textContent =
    SELECTOR + '.rt-ready{min-width:0!important}' +
    SELECTOR + ' .rt-hidden{display:none!important}' +
    // While measuring, cells must not wrap: a column only stays visible if
    // all visible columns fit side by side without squeezing their text.
    // The first column (never hidden) may wrap, but keeps a minimum width
    // set in layout().
    SELECTOR + '.rt-measuring>*>tr>*{white-space:nowrap!important}' +
    SELECTOR + '.rt-measuring>*>tr>:first-child{white-space:normal!important}' +
    '.rt-toggle{display:inline-flex;align-items:center;justify-content:center;flex:none;width:1.25rem;height:1.25rem;margin:-0.125rem 0.5rem -0.125rem 0;' +
    'vertical-align:middle;border-radius:9999px;border:0;padding:0;cursor:pointer;color:#fff;' +
    'background:rgb(var(--color-brand-600,37 99 235));font:600 0.875rem/1 system-ui,sans-serif;' +
    'box-shadow:0 0 0 2px rgba(255,255,255,.9);transition:transform .15s ease,background-color .15s ease}' +
    '.dark .rt-toggle{box-shadow:0 0 0 2px rgba(15,23,42,.9)}' +
    '.rt-toggle::before{content:"+"}' +
    '.rt-toggle:focus-visible{outline:2px solid rgb(var(--color-brand-500,59 130 246));outline-offset:2px}' +
    'tr.rt-open .rt-toggle{background:#dc2626;transform:rotate(180deg)}' +
    'tr.rt-open .rt-toggle::before{content:"\\2212"}' +
    // Enlarge the tap target without changing the visual size.
    '.rt-toggle{position:relative}.rt-toggle::after{content:"";position:absolute;inset:-0.625rem}' +
    'tr.rt-open>td,tr.rt-open>th{border-bottom-width:0}' +
    'tr.rt-child>td{padding:0 1rem 0.875rem 2.75rem!important}' +
    '.rt-details{display:grid;grid-template-columns:minmax(0,max-content) minmax(0,1fr);gap:0.375rem 1rem;margin:0;font-size:0.875rem}' +
    '.rt-details dt{font-size:0.75rem;font-weight:500;text-transform:uppercase;letter-spacing:.025em;color:#64748b;padding-top:0.125rem}' +
    '.rt-details dd{margin:0;min-width:0;overflow-wrap:anywhere;color:#334155}' +
    '.rt-details dd.rt-full{grid-column:1/-1}' +
    '.dark .rt-details dt{color:#94a3b8}.dark .rt-details dd{color:#cbd5e1}' +
    // Cell utility classes like whitespace-nowrap/font-mono stay on the
    // clone's wrapper; padding does not.
    '.rt-details dd>.rt-value{padding:0!important;text-align:left!important}';
  document.head.appendChild(style);

  function headerRow(table) {
    return table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
  }

  function dataRows(table) {
    var rows = [];
    Array.prototype.forEach.call(table.tBodies, function (tbody) {
      Array.prototype.forEach.call(tbody.rows, function (row) {
        if (!row.classList.contains('rt-child') && row.cells.length > 1) { rows.push(row); }
      });
    });
    return rows;
  }

  // Indices of hideable columns, in the order they get hidden.
  function hideOrder(table) {
    var cells = Array.prototype.slice.call(headerRow(table).cells);
    var order = [];
    cells.forEach(function (th, index) {
      if (index === 0) { return; }
      var raw = th.getAttribute('data-priority');
      var priority = raw === null || raw === '' ? Infinity : parseInt(raw, 10);
      if (priority === 0) { return; }
      order.push({ index: index, priority: isNaN(priority) ? Infinity : priority });
    });
    order.sort(function (a, b) { return b.priority - a.priority || b.index - a.index; });
    return order.map(function (entry) { return entry.index; });
  }

  function setColumnHidden(table, index, hidden) {
    var head = headerRow(table);
    if (head.cells[index]) { head.cells[index].classList.toggle('rt-hidden', hidden); }
    dataRows(table).forEach(function (row) {
      if (row.cells[index]) { row.cells[index].classList.toggle('rt-hidden', hidden); }
    });
  }

  function hiddenColumns(table) {
    var result = [];
    Array.prototype.forEach.call(headerRow(table).cells, function (th, index) {
      if (th.classList.contains('rt-hidden')) { result.push(index); }
    });
    return result;
  }

  function visibleColumnCount(table) {
    return headerRow(table).cells.length - hiddenColumns(table).length;
  }

  function buildDetails(table, row) {
    var head = headerRow(table);
    var list = document.createElement('dl');
    list.className = 'rt-details';
    hiddenColumns(table).forEach(function (index) {
      var cell = row.cells[index];
      if (!cell || cell.textContent.trim() === '' && !cell.querySelector('a, button, img, svg, input')) { return; }
      var title = head.cells[index].textContent.trim();
      var value = document.createElement('dd');
      var wrapper = document.createElement('div');
      wrapper.className = cell.className.replace(/\brt-hidden\b/g, '') + ' rt-value';
      Array.prototype.forEach.call(cell.childNodes, function (node) { wrapper.appendChild(node.cloneNode(true)); });
      value.appendChild(wrapper);
      if (title === '') {
        value.className = 'rt-full';
      } else {
        var term = document.createElement('dt');
        term.textContent = title;
        list.appendChild(term);
      }
      list.appendChild(value);
    });
    return list;
  }

  function closeRow(row) {
    row.classList.remove('rt-open');
    var toggle = row.querySelector('.rt-toggle');
    if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
    if (row.__rtChild) { row.__rtChild.remove(); row.__rtChild = null; }
  }

  function openRow(table, row) {
    var child = row.__rtChild;
    if (!child) {
      child = document.createElement('tr');
      child.className = 'rt-child';
      child.appendChild(document.createElement('td'));
      row.__rtChild = child;
    }
    var cell = child.cells[0];
    cell.colSpan = visibleColumnCount(table);
    cell.textContent = '';
    cell.appendChild(buildDetails(table, row));
    row.parentNode.insertBefore(child, row.nextSibling);
    row.classList.add('rt-open');
    var toggle = row.querySelector('.rt-toggle');
    if (toggle) { toggle.setAttribute('aria-expanded', 'true'); }
  }

  function updateToggles(table) {
    var needed = hiddenColumns(table).length > 0;
    dataRows(table).forEach(function (row) {
      var toggle = row.querySelector('.rt-toggle');
      if (needed && !toggle) {
        toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'rt-toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Details anzeigen');
        row.cells[0].insertBefore(toggle, row.cells[0].firstChild);
      } else if (!needed && toggle) {
        toggle.remove();
      }
      if (!needed) {
        closeRow(row);
      } else if (row.classList.contains('rt-open')) {
        openRow(table, row); // refresh contents for the new set of hidden columns
      }
    });
  }

  function layout(table) {
    var container = table.parentElement;
    var available = container.clientWidth;
    if (available === 0) { return; } // not rendered (e.g. inside a closed <details>)
    if (table.__rtWidth === available) { return; }
    table.__rtWidth = available;

    var order = hideOrder(table);
    order.forEach(function (index) { setColumnHidden(table, index, false); });
    table.classList.add('rt-measuring');
    // First column: its unwrapped width, but at most 45 % of the space.
    var first = headerRow(table).cells[0];
    first.style.whiteSpace = 'nowrap';
    first.style.minWidth = '';
    dataRows(table).forEach(function (row) { row.cells[0].style.whiteSpace = 'nowrap'; });
    var firstWidth = 0;
    dataRows(table).concat([headerRow(table)]).forEach(function (row) {
      firstWidth = Math.max(firstWidth, row.cells[0].scrollWidth);
    });
    dataRows(table).forEach(function (row) { row.cells[0].style.whiteSpace = ''; });
    first.style.whiteSpace = '';
    first.style.minWidth = Math.min(firstWidth + 32, Math.round(available * 0.45)) + 'px';
    var i = 0;
    for (; i < order.length && table.offsetWidth > available; i++) {
      setColumnHidden(table, order[i], true);
    }
    // The toggle button added to the first column takes space as well.
    if (i > 0) {
      updateToggles(table);
      for (; i < order.length && table.offsetWidth > available; i++) {
        setColumnHidden(table, order[i], true);
      }
    }
    table.classList.remove('rt-measuring');
    first.style.minWidth = '';
    updateToggles(table);
  }

  function init(table) {
    if (table.__rtInit || !headerRow(table)) { return; }
    table.__rtInit = true;
    table.classList.add('rt-ready');
    layout(table);
    if (window.ResizeObserver) {
      new ResizeObserver(function () { layout(table); }).observe(table.parentElement);
    } else {
      window.addEventListener('resize', function () { layout(table); });
    }
  }

  function initAll(root) {
    Array.prototype.forEach.call((root || document).querySelectorAll(SELECTOR), init);
  }

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest && event.target.closest('.rt-toggle');
    if (!toggle) { return; }
    var row = toggle.closest('tr');
    var table = row && row.closest(SELECTOR);
    if (!table) { return; }
    event.preventDefault();
    if (row.classList.contains('rt-open')) { closeRow(row); } else { openRow(table, row); }
  });

  // Before sortable-tables.js reorders the rows (its listener runs in the
  // bubble phase), take the detail rows out so all rows have the same cell
  // count, then put them back below their (moved) parent rows.
  document.addEventListener('click', function (event) {
    var th = event.target.closest && event.target.closest('thead th');
    var table = th && th.closest(SELECTOR);
    if (!table) { return; }
    var open = dataRows(table).filter(function (row) { return row.__rtChild && row.__rtChild.parentNode; });
    open.forEach(function (row) { row.__rtChild.remove(); });
    setTimeout(function () {
      open.forEach(function (row) { row.parentNode.insertBefore(row.__rtChild, row.nextSibling); });
    }, 0);
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(); });
  } else {
    initAll();
  }

  // Tables loaded later (AJAX) can be initialised with ResponsiveTables.init(container).
  window.ResponsiveTables = { init: initAll };
})();
