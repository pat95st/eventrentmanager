// Runs inside the <iframe> of the form modal (see form-modal.js): loaded by
// layout.php only when the page is rendered for the modal ($modalFrame).
(function () {
  'use strict';

  var PARAM = '_modal';

  var host = null;
  try {
    host = window.parent !== window ? window.parent.FormModal || null : null;
  } catch (e) {
    host = null;
  }

  if (window.top === window.self) {
    // Opened directly (e.g. a copied address): show the regular page.
    var own = new URL(window.location.href);
    if (own.searchParams.has(PARAM)) {
      own.searchParams.delete(PARAM);
      window.location.replace(own.href);
    }
    return;
  }

  // Mark every POST as coming from the modal, so a form rendered again after
  // a rejected submit stays in the modal layout (see ModalFrame::detect()).
  function mark(form) {
    if (!form || form.tagName !== 'FORM' || (form.getAttribute('method') || '').toLowerCase() !== 'post') {
      return;
    }
    if (form.target && form.target !== '_self') {
      return;
    }
    if (!form.querySelector('input[name="' + PARAM + '"]')) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = PARAM;
      input.value = '1';
      form.appendChild(input);
    }
  }
  Array.prototype.forEach.call(document.forms, mark);
  document.addEventListener('submit', function (e) { mark(e.target); }, true);

  if (host === null) {
    return;
  }

  // Height of the page, so the dialog fits short forms.
  function reportHeight() {
    host.setHeight(document.body.offsetHeight);
  }
  reportHeight();
  if (window.ResizeObserver) {
    new ResizeObserver(reportHeight).observe(document.body);
  } else {
    window.addEventListener('load', reportHeight);
  }

  // Unsaved input: the modal asks before closing.
  ['input', 'change'].forEach(function (type) {
    document.addEventListener(type, function (e) {
      if (e.isTrusted) {
        host.setDirty();
      }
    }, true);
  });

  window.addEventListener('pagehide', function () { host.setLoading(); });

  // Escape closes the modal — unless a dialog/overlay of the form itself is
  // open, or a field has the focus (first Escape leaves the field).
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || e.defaultPrevented) {
      return;
    }
    if (document.querySelector('dialog[open], .fixed.inset-0:not(.hidden)')) {
      return;
    }
    var active = document.activeElement;
    if (active && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) {
      active.blur();
      return;
    }
    host.requestClose();
  });

  // Links: "back"/"cancel" to the page below the modal just closes it; other
  // pages open in the top-level window instead of inside the modal.
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
      return;
    }
    var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) {
      return;
    }
    var url;
    try {
      url = new URL(link.href);
    } catch (err) {
      return;
    }
    if (!/^https?:$/.test(url.protocol)) {
      return;
    }
    var here = window.location;
    if (url.origin === here.origin && url.pathname === here.pathname && url.search.replace(/[?&]_modal=1/, '') === here.search.replace(/[?&]_modal=1/, '')) {
      return; // Anchor within this page.
    }
    var below = window.parent.location;
    e.preventDefault();
    if (url.origin === below.origin && url.pathname === below.pathname && url.search === below.search) {
      host.requestClose();
    } else {
      window.top.location.href = url.href;
    }
  });
})();
