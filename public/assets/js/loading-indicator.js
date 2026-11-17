// Global loading indicator.
//
// Shows that something is still loading whenever an action takes noticeably
// long (after a short delay, so fast actions do not flicker):
//  - a thin progress bar at the top of the page while a page navigation
//    (link click, form submit) or a fetch()/XMLHttpRequest is pending,
//  - a large spinner in the centre of the page while a page navigation is
//    pending (not for background requests, which must not block the page),
//  - a spinner inside the submit button of a form that was submitted.
//
// The large spinner never traps the user: a click on it or Escape dismisses
// it, and it hides itself after a while (e.g. when the "navigation" turned
// out to be a file download and the page never unloads).
//
// Opt out per element with data-no-loading-indicator on a form or link.
//
// API for own scripts:
//  - window.loadingIndicator.start() / .stop()       top bar (counted)
//  - window.loadingIndicator.setButtonLoading(btn, on) spinner in a button
//  - window.loadingIndicator.spinnerHtml(extraClass)   markup of a spinner
//    (element with class "loading-spinner", size follows the font size)
//  - window.loadingIndicator.showOverlay({label, detail, maxMs}) / .hideOverlay()
//    large centred spinner for long-running actions of own scripts (e.g. a
//    form with data-no-loading-indicator); label/detail replace the default
//    text, maxMs replaces the 30 s limit. The returned element's
//    ".page-loading-detail" can be updated (e.g. with an elapsed time).
(function () {
  'use strict';

  if (window.loadingIndicator) {
    return;
  }

  var DELAY_MS = 400;
  // A form submit or link may answer with a download, in which case the page
  // never unloads. Reset the indicator after this time at the latest.
  var NAVIGATION_RESET_MS = 120000;
  // The large spinner covers the page, so it gives up much earlier.
  var OVERLAY_MAX_MS = 30000;
  // Targets that usually answer with a file instead of a page.
  var DOWNLOAD_URL_PATTERN = /(\.(csv|pdf|zip|xml|ics|xlsx?|json|txt|vcf|mobileconfig|png|jpe?g|gif|webp|svg)|\/(download|export))$/i;

  var style = document.createElement('style');
  style.textContent = [
    '@keyframes loading-spinner-rotate { to { transform: rotate(360deg); } }',
    '@keyframes loading-bar-slide { 0% { left: -40%; } 100% { left: 100%; } }',
    '.loading-spinner { display: inline-block; width: 1em; height: 1em; flex-shrink: 0; vertical-align: -0.125em;',
    '  border: 2px solid currentColor; border-right-color: transparent; border-radius: 9999px;',
    '  animation: loading-spinner-rotate 0.7s linear infinite; }',
    '.loading-bar { position: fixed; top: 0; left: 0; right: 0; height: 3px; z-index: 10000; overflow: hidden;',
    '  pointer-events: none; opacity: 0; transition: opacity 0.2s; }',
    '.loading-bar.is-active { opacity: 1; }',
    '.loading-bar::before { content: ""; position: absolute; top: 0; bottom: 0; width: 40%;',
    '  background-color: rgb(var(--color-brand-500, 59 130 246)); animation: loading-bar-slide 1.1s ease-in-out infinite; }',
    '@keyframes page-loading-in { from { opacity: 0; } to { opacity: 1; } }',
    '@keyframes page-loading-materialize { from { transform: scale(0.9); } to { transform: scale(1); } }',
    // The resets (inset, size, margin, border, padding) override the browser
    // defaults of [popover], see showOverlay().
    '.page-loading { position: fixed; inset: 0; width: 100%; height: 100%; max-width: none; max-height: none;',
    '  margin: 0; border: 0; padding: 1rem; z-index: 9999; display: none; flex-direction: column;',
    '  align-items: center; justify-content: center; gap: 1rem; cursor: progress; overflow: hidden;',
    '  color: rgb(51 65 85); background-color: rgb(248 250 252 / 0.6);',
    '  -webkit-backdrop-filter: blur(6px) saturate(140%); backdrop-filter: blur(6px) saturate(140%); }',
    '.page-loading::backdrop { background: transparent; }',
    '.page-loading.is-active { display: flex; animation: page-loading-in 0.2s ease-out both; }',
    '.dark .page-loading { color: rgb(226 232 240); background-color: rgb(2 6 23 / 0.6); }',
    '.page-loading-spinner { width: 4.5rem; height: 4.5rem; border-radius: 9999px;',
    '  border: 0.375rem solid rgb(var(--color-brand-500, 59 130 246) / 0.2);',
    '  border-top-color: rgb(var(--color-brand-500, 59 130 246));',
    '  animation: loading-spinner-rotate 0.8s linear infinite, page-loading-materialize 0.3s ease-out both; }',
    '.page-loading-label { font-size: 0.9375rem; font-weight: 600; letter-spacing: 0.01em; text-align: center; }',
    '.page-loading-detail { font-size: 0.8125rem; opacity: 0.75; text-align: center; max-width: 28rem; font-variant-numeric: tabular-nums; }',
    '.page-loading-detail:empty { display: none; }',
    '.is-loading-button { cursor: progress !important; }',
    '.is-loading-button > .loading-spinner { margin-right: 0.375rem; }',
    '@media (prefers-reduced-motion: reduce) {',
    '  .loading-spinner { animation-duration: 1.6s; }',
    '  .loading-bar::before { animation-duration: 2.5s; }',
    '  .page-loading.is-active { animation: none; }',
    '  .page-loading-spinner { animation: loading-spinner-rotate 1.6s linear infinite; }',
    '}',
    '@media (prefers-reduced-transparency: reduce), (prefers-contrast: more) {',
    '  .page-loading { background-color: rgb(248 250 252); -webkit-backdrop-filter: none; backdrop-filter: none; }',
    '  .dark .page-loading { background-color: rgb(2 6 23); }',
    '}',
  ].join('\n');
  document.head.appendChild(style);

  function spinnerHtml(extraClass) {
    return '<span class="loading-spinner' + (extraClass ? ' ' + extraClass : '') + '" role="status" aria-label="Wird geladen …"></span>';
  }

  // --- Top bar ------------------------------------------------------------
  var bar = null;
  var pending = 0;
  var showTimer = null;

  function ensureBar() {
    if (!bar && document.body) {
      bar = document.createElement('div');
      bar.className = 'loading-bar';
      bar.setAttribute('aria-hidden', 'true');
      document.body.appendChild(bar);
    }
    return bar;
  }

  function start() {
    pending++;
    if (pending === 1 && showTimer === null) {
      showTimer = setTimeout(function () {
        showTimer = null;
        if (pending > 0 && ensureBar()) {
          bar.classList.add('is-active');
        }
      }, DELAY_MS);
    }
  }

  function stop() {
    pending = Math.max(0, pending - 1);
    if (pending === 0) {
      clearTimeout(showTimer);
      showTimer = null;
      if (bar) {
        bar.classList.remove('is-active');
      }
    }
  }

  // --- Large spinner in the centre of the page -----------------------------
  var overlay = null;
  var overlayShowTimer = null;
  var overlayHideTimer = null;

  function loadingLabel() {
    var lang = (document.documentElement.getAttribute('lang') || 'de').toLowerCase();
    return lang.indexOf('en') === 0 ? 'Loading …' : 'Wird geladen …';
  }

  function ensureOverlay() {
    if (!overlay && document.body) {
      overlay = document.createElement('div');
      overlay.className = 'page-loading';
      overlay.setAttribute('role', 'status');
      overlay.setAttribute('aria-live', 'polite');
      var spinner = document.createElement('span');
      spinner.className = 'page-loading-spinner';
      spinner.setAttribute('aria-hidden', 'true');
      var label = document.createElement('span');
      label.className = 'page-loading-label';
      label.textContent = loadingLabel();
      var detail = document.createElement('span');
      detail.className = 'page-loading-detail';
      overlay.appendChild(spinner);
      overlay.appendChild(label);
      overlay.appendChild(detail);
      // Never trap the user behind the spinner.
      overlay.addEventListener('click', hideOverlay);
      if (typeof overlay.showPopover === 'function') {
        // Top layer: also covers modal dialogs opened with showModal().
        overlay.setAttribute('popover', 'manual');
      }
      document.body.appendChild(overlay);
    }
    return overlay;
  }

  function setOverlayText(label, detail) {
    overlay.querySelector('.page-loading-label').textContent = label || loadingLabel();
    overlay.querySelector('.page-loading-detail').textContent = detail || '';
  }

  function showOverlay(options) {
    if (!ensureOverlay()) {
      return null;
    }
    // Called from a timer with no options for page navigations.
    var opts = options && typeof options === 'object' ? options : {};
    setOverlayText(opts.label, opts.detail);
    overlay.classList.add('is-active');
    if (overlay.hasAttribute('popover')) {
      try {
        overlay.showPopover();
      } catch (err) {
        // Already open or not connected: the class alone still shows it.
      }
    }
    clearTimeout(overlayHideTimer);
    overlayHideTimer = setTimeout(hideOverlay, opts.maxMs > 0 ? opts.maxMs : OVERLAY_MAX_MS);
    return overlay;
  }

  function hideOverlay() {
    clearTimeout(overlayShowTimer);
    clearTimeout(overlayHideTimer);
    overlayShowTimer = null;
    if (!overlay) {
      return;
    }
    overlay.classList.remove('is-active');
    if (overlay.hasAttribute('popover')) {
      try {
        overlay.hidePopover();
      } catch (err) {
        // Was not open.
      }
    }
  }

  function scheduleOverlay() {
    hideOverlay();
    overlayShowTimer = setTimeout(function () {
      showOverlay();
    }, DELAY_MS);
  }

  window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay && overlay.classList.contains('is-active')) {
      hideOverlay();
    }
  });

  function looksLikeDownload(url) {
    if (!url) {
      return false;
    }
    try {
      return DOWNLOAD_URL_PATTERN.test(new URL(url, window.location.href).pathname);
    } catch (err) {
      return false;
    }
  }

  // --- Buttons ------------------------------------------------------------
  function setButtonLoading(button, on) {
    if (!button || button.tagName !== 'BUTTON') {
      return;
    }
    var existing = button.querySelector(':scope > .loading-spinner');
    if (on) {
      if (!existing) {
        button.insertAdjacentHTML('afterbegin', spinnerHtml());
      }
      button.classList.add('is-loading-button');
      button.setAttribute('aria-busy', 'true');
    } else {
      if (existing) {
        existing.remove();
      }
      button.classList.remove('is-loading-button');
      button.removeAttribute('aria-busy');
    }
  }

  // --- Page navigations ---------------------------------------------------
  var navigating = false;
  var navigationButton = null;
  var navigationButtonTimer = null;
  var navigationResetTimer = null;
  // Set while an opted-out link/form is being handled, so the Navigation API
  // listener below does not start the indicator for it after all.
  var suppressed = false;

  function suppressBriefly() {
    suppressed = true;
    setTimeout(function () {
      suppressed = false;
    }, 0);
  }

  function endNavigation() {
    clearTimeout(navigationButtonTimer);
    clearTimeout(navigationResetTimer);
    hideOverlay();
    setButtonLoading(navigationButton, false);
    navigationButton = null;
    if (navigating) {
      navigating = false;
      stop();
    }
  }

  function beginNavigation(button, url) {
    endNavigation();
    navigating = true;
    start();
    if (!looksLikeDownload(url)) {
      scheduleOverlay();
    }
    navigationButton = button || null;
    if (navigationButton) {
      navigationButtonTimer = setTimeout(function () {
        setButtonLoading(navigationButton, true);
      }, DELAY_MS);
    }
    navigationResetTimer = setTimeout(endNavigation, NAVIGATION_RESET_MS);
  }

  function opensElsewhere(target) {
    return target !== null && target !== '' && target !== '_self' && target !== '_top' && target !== '_parent';
  }

  function submitterFor(form, submitter) {
    if (submitter && submitter.tagName === 'BUTTON') {
      return submitter;
    }
    return form.querySelector('button[type="submit"], button:not([type])');
  }

  function handleFormSubmit(form, submitter) {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    if (form.hasAttribute('data-no-loading-indicator')) {
      suppressBriefly();
      return;
    }
    var target = (submitter && submitter.getAttribute('formtarget')) || form.getAttribute('target');
    if (opensElsewhere(target)) {
      return;
    }
    if ((form.getAttribute('method') || '').toLowerCase() === 'dialog') {
      return;
    }
    var action = (submitter && submitter.getAttribute('formaction')) || form.getAttribute('action') || '';
    beginNavigation(submitterFor(form, submitter), action);
  }

  // Bubbling phase on window: runs after all other submit handlers, so a
  // prevented submit (validation, confirm modal, fetch-based forms) is
  // recognisable via defaultPrevented.
  window.addEventListener('submit', function (e) {
    if (!e.defaultPrevented) {
      handleFormSubmit(e.target, e.submitter);
    }
  });

  // form.submit() fires no submit event (used e.g. after the confirm modal).
  var originalSubmit = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function () {
    handleFormSubmit(this, null);
    return originalSubmit.apply(this, arguments);
  };

  window.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
      return;
    }
    var link = e.target.closest && e.target.closest('a[href]');
    if (!link) {
      return;
    }
    if (link.hasAttribute('download') || link.hasAttribute('data-no-loading-indicator')) {
      suppressBriefly();
      return;
    }
    if (opensElsewhere(link.getAttribute('target'))) {
      return;
    }
    var href = link.getAttribute('href');
    if (href === '' || href.charAt(0) === '#' || /^(javascript|mailto|tel|sms):/i.test(href)) {
      return;
    }
    var url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (err) {
      return;
    }
    if (url.origin !== window.location.origin) {
      return;
    }
    // Same page, only a different anchor: no navigation.
    if (url.hash !== '' && url.pathname === window.location.pathname && url.search === window.location.search) {
      return;
    }
    beginNavigation(null, url.href);
  });

  // Navigations started by scripts (clickable table rows, redirects via
  // location.href, ...) fire neither a link click nor a submit event; the
  // Navigation API reports them where the browser supports it.
  if (window.navigation && typeof window.navigation.addEventListener === 'function') {
    window.navigation.addEventListener('navigate', function (e) {
      if (navigating || suppressed || e.hashChange || e.downloadRequest != null) {
        return;
      }
      var destination = e.destination;
      if (!destination || destination.sameDocument) {
        return;
      }
      try {
        if (new URL(destination.url).origin !== window.location.origin) {
          return;
        }
      } catch (err) {
        return;
      }
      beginNavigation(null, destination.url);
    });
  }

  // Back/forward cache: the restored page must not keep showing the indicator.
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      endNavigation();
    }
  });

  // --- Background requests ------------------------------------------------
  if (window.fetch) {
    var originalFetch = window.fetch;
    window.fetch = function () {
      start();
      var result;
      try {
        result = originalFetch.apply(this, arguments);
      } catch (err) {
        stop();
        throw err;
      }
      return Promise.resolve(result).then(function (response) {
        stop();
        return response;
      }, function (error) {
        stop();
        throw error;
      });
    };
  }

  var originalSend = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.send = function () {
    var xhr = this;
    var done = false;
    var finish = function () {
      if (!done) {
        done = true;
        stop();
      }
    };
    xhr.addEventListener('loadend', finish);
    start();
    try {
      return originalSend.apply(xhr, arguments);
    } catch (err) {
      finish();
      throw err;
    }
  };

  window.loadingIndicator = {
    start: start,
    stop: stop,
    setButtonLoading: setButtonLoading,
    spinnerHtml: spinnerHtml,
    showOverlay: showOverlay,
    hideOverlay: hideOverlay,
  };
})();
