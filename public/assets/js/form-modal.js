// Create/edit pages in a modal on large screens.
//
// Every link to a form page ("…/neu", "…/neues-bundle", "…/bearbeiten") opens
// that page in a <dialog> instead of navigating there — on screens from
// 1024px. The dialog holds an <iframe> with the regular page, rendered
// without navigation/footer (see App\Infrastructure\Http\ModalFrame and
// $modalFrame in layout.php), so each form keeps its own markup, scripts and
// nested dialogs. On smaller screens, or with data-no-modal on the link or
// one of its ancestors, the link works as before.
//
// After a successful save the server would redirect to a regular page; for
// requests from the modal Response::redirect() answers with a page that moves
// this (top-level) window there instead, exactly as if the form had been
// submitted on its own page.
//
// Counterpart inside the iframe: form-modal-frame.js.
(function () {
  'use strict';

  if (window.top !== window.self || !window.HTMLDialogElement || !window.URL) {
    return;
  }

  // Keep in sync with ModalFrame::FORM_PATH_PATTERN.
  var FORM_PATH = /\/(neu|neues-bundle|bearbeiten)\/?$/;
  var PARAM = '_modal';
  var large = window.matchMedia('(min-width: 1024px)');
  var i18n = window.FORM_MODAL_I18N || {};

  var dialog = null;
  var frame = null;
  var fullLink = null;
  var state = null; // { loads: number, dirty: boolean, height: number }

  function formUrl(href) {
    var url;
    try {
      url = new URL(href, window.location.href);
    } catch (e) {
      return null;
    }
    if (url.origin !== window.location.origin || !FORM_PATH.test(url.pathname)) {
      return null;
    }
    return url;
  }

  function icon(path) {
    return '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + path + '"/></svg>';
  }

  function build() {
    dialog = document.createElement('dialog');
    dialog.id = 'form-modal';
    dialog.className = 'modal-panel form-modal bg-white dark:bg-slate-900';

    var bar = document.createElement('div');
    bar.className = 'form-modal-bar';

    fullLink = document.createElement('a');
    fullLink.setAttribute('data-no-modal', '');
    fullLink.title = i18n.openFullPage || '';
    fullLink.setAttribute('aria-label', i18n.openFullPage || '');
    fullLink.innerHTML = icon('M12 4h4v4M16 4l-5 5M8 16H4v-4M4 16l5-5');

    var closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.title = i18n.close || '';
    closeButton.setAttribute('aria-label', i18n.close || '');
    closeButton.innerHTML = icon('M5 5l10 10M15 5L5 15');
    closeButton.addEventListener('click', requestClose);

    bar.appendChild(fullLink);
    bar.appendChild(closeButton);

    var spinner = document.createElement('div');
    spinner.className = 'form-modal-spinner';

    frame = document.createElement('iframe');
    frame.className = 'form-modal-frame';
    frame.addEventListener('load', onFrameLoad);

    dialog.appendChild(bar);
    dialog.appendChild(spinner);
    dialog.appendChild(frame);
    document.body.appendChild(dialog);

    // Click on the backdrop (the dialog element itself, outside its content).
    dialog.addEventListener('mousedown', function (e) {
      if (e.target === dialog) {
        requestClose();
      }
    });
    dialog.addEventListener('close', function () {
      var reload = state !== null && state.loads > 1;
      state = null;
      frame.removeAttribute('src');
      document.documentElement.style.overflow = '';
      if (reload) {
        // Something was saved while the modal stayed open.
        window.location.reload();
      }
    });
    window.addEventListener('resize', applyHeight);
  }

  // Escape: ask before discarding input. Registered on window in the capture
  // phase to run before the generic handler in modal-fade.js.
  window.addEventListener('cancel', function (e) {
    if (dialog !== null && e.target === dialog) {
      e.preventDefault();
      e.stopImmediatePropagation();
      requestClose();
    }
  }, true);

  function applyHeight() {
    if (state === null) {
      return;
    }
    var rem = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
    var max = Math.max(200, window.innerHeight - 4 * rem);
    var min = Math.min(max, 26 * rem);
    frame.style.height = Math.round(Math.max(min, Math.min(max, state.height))) + 'px';
  }

  function onFrameLoad() {
    if (state === null) {
      return;
    }
    var doc = null;
    var href = '';
    try {
      doc = frame.contentDocument;
      href = frame.contentWindow.location.href;
    } catch (e) {
      doc = null;
    }
    if (href === '' || href === 'about:blank') {
      return;
    }
    var root = doc ? doc.documentElement : null;
    if (root && root.hasAttribute('data-modal-breakout')) {
      return; // That page moves this window itself.
    }
    if (!root || !root.hasAttribute('data-modal-frame')) {
      // A page outside the layout (e.g. the login after a session timeout).
      window.location.href = href;
      return;
    }

    state.loads += 1;
    // A form shown again after a rejected submit still holds unsaved input.
    state.dirty = root.getAttribute('data-modal-frame') === 'post';
    var full = new URL(href);
    full.searchParams.delete(PARAM);
    fullLink.href = full.pathname + full.search + full.hash;
    dialog.classList.remove('is-loading');
    try {
      frame.contentWindow.focus();
    } catch (e) { /* ignore */ }
  }

  function open(url) {
    if (dialog === null) {
      build();
    }
    if (dialog.open) {
      return;
    }
    var target = new URL(url.href);
    target.searchParams.set(PARAM, '1');

    state = { loads: 0, dirty: false, height: 0 };
    fullLink.href = url.pathname + url.search + url.hash;
    dialog.classList.add('is-loading');
    applyHeight();
    frame.src = target.pathname + target.search + target.hash;
    document.documentElement.style.overflow = 'hidden';
    dialog.showModal();
  }

  function close() {
    if (dialog !== null && dialog.open) {
      dialog.close();
    }
  }

  function requestClose() {
    if (state === null) {
      return;
    }
    if (!state.dirty || typeof window.confirmModal !== 'function') {
      close();
      return;
    }
    window.confirmModal(i18n.discardQuestion || '', 'danger', { okLabel: i18n.discardOk || '' }).then(function (ok) {
      if (ok) {
        close();
      }
    });
  }

  /** Opens href in the modal if it is a form page and the screen is large. */
  function tryOpen(href) {
    if (!large.matches) {
      return false;
    }
    var url = formUrl(href);
    if (url === null) {
      return false;
    }
    open(url);
    return true;
  }

  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
      return;
    }
    var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self') || link.closest('[data-no-modal]')) {
      return;
    }
    if (tryOpen(link.href)) {
      e.preventDefault();
    }
  });

  // Used by form-modal-frame.js (inside the iframe) and row-links.js.
  window.FormModal = {
    tryOpen: tryOpen,
    requestClose: requestClose,
    setDirty: function () {
      if (state !== null) {
        state.dirty = true;
      }
    },
    setHeight: function (height) {
      if (state !== null) {
        state.height = height;
        applyHeight();
      }
    },
    setLoading: function () {
      if (state !== null && dialog !== null) {
        dialog.classList.add('is-loading');
      }
    },
  };
})();
