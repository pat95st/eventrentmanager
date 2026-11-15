// Global loading indicator.
//
// Shows that something is still loading whenever an action takes noticeably
// long (after a short delay, so fast actions do not flicker):
//  - a thin progress bar at the top of the page while a page navigation
//    (link click, form submit) or a fetch()/XMLHttpRequest is pending,
//  - a spinner inside the submit button of a form that was submitted.
//
// Opt out per element with data-no-loading-indicator on a form or link.
//
// API for own scripts:
//  - window.loadingIndicator.start() / .stop()       top bar (counted)
//  - window.loadingIndicator.setButtonLoading(btn, on) spinner in a button
//  - window.loadingIndicator.spinnerHtml(extraClass)   markup of a spinner
//    (element with class "loading-spinner", size follows the font size)
(function () {
  'use strict';

  if (window.loadingIndicator) {
    return;
  }

  var DELAY_MS = 400;
  // A form submit or link may answer with a download, in which case the page
  // never unloads. Reset the indicator after this time at the latest.
  var NAVIGATION_RESET_MS = 120000;

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
    '.is-loading-button { cursor: progress !important; }',
    '.is-loading-button > .loading-spinner { margin-right: 0.375rem; }',
    '@media (prefers-reduced-motion: reduce) {',
    '  .loading-spinner { animation-duration: 1.6s; }',
    '  .loading-bar::before { animation-duration: 2.5s; }',
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

  function endNavigation() {
    clearTimeout(navigationButtonTimer);
    clearTimeout(navigationResetTimer);
    setButtonLoading(navigationButton, false);
    navigationButton = null;
    if (navigating) {
      navigating = false;
      stop();
    }
  }

  function beginNavigation(button) {
    endNavigation();
    navigating = true;
    start();
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
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading-indicator')) {
      return;
    }
    var target = (submitter && submitter.getAttribute('formtarget')) || form.getAttribute('target');
    if (opensElsewhere(target)) {
      return;
    }
    if ((form.getAttribute('method') || '').toLowerCase() === 'dialog') {
      return;
    }
    beginNavigation(submitterFor(form, submitter));
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
    if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-loading-indicator') || opensElsewhere(link.getAttribute('target'))) {
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
    beginNavigation(null);
  });

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
  };
})();
