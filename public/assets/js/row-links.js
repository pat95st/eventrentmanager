// Clickable list rows: any element with data-row-link="<url>" (usually a
// <tr>, but also list items or cards) opens that URL when clicked anywhere
// in the row.
//
// - Clicks on interactive elements inside the row (links, buttons, form
//   fields, labels, <details>) keep their own behaviour.
// - Selecting text inside the row does not navigate.
// - Ctrl/Cmd-click and middle-click open the URL in a new tab, like a link.
// - Works for rows loaded later via AJAX (event delegation).
(function () {
  'use strict';

  var INTERACTIVE = 'a, button, input, select, textarea, label, summary, details, form, [contenteditable], [data-row-link-ignore]';

  var style = document.createElement('style');
  style.textContent = '[data-row-link]{cursor:pointer}';
  document.head.appendChild(style);

  function targetRow(e) {
    if (!e.target || !e.target.closest) {
      return null;
    }
    var row = e.target.closest('[data-row-link]');
    if (!row) {
      return null;
    }
    var interactive = e.target.closest(INTERACTIVE);
    if (interactive && row.contains(interactive)) {
      return null;
    }
    var selection = window.getSelection ? window.getSelection() : null;
    if (selection && !selection.isCollapsed && row.contains(selection.anchorNode)) {
      return null;
    }
    return row;
  }

  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0) {
      return;
    }
    var row = targetRow(e);
    if (!row) {
      return;
    }
    var url = row.getAttribute('data-row-link');
    if (!url) {
      return;
    }
    if (e.ctrlKey || e.metaKey || e.shiftKey) {
      window.open(url, '_blank', 'noopener');
      return;
    }
    window.location.href = url;
  });

  document.addEventListener('auxclick', function (e) {
    if (e.defaultPrevented || e.button !== 1) {
      return;
    }
    var row = targetRow(e);
    if (row && row.getAttribute('data-row-link')) {
      e.preventDefault();
      window.open(row.getAttribute('data-row-link'), '_blank', 'noopener');
    }
  });
})();
