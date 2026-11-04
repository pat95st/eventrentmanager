// Sichtbare Fehlermeldungen, wenn etwas nicht geladen werden kann.
//
// In der installierten App (PWA) gibt es keine Browser-Leiste mit Lade-
// anzeige oder Fehlerseite – schlägt ein Abruf fehl, passiert scheinbar
// einfach nichts. Dieses Skript zeigt deshalb eine Meldung unten am Rand:
//  - Nachladen per fetch()/XMLHttpRequest (Listen, Suche, Picker, PDF …)
//    ohne Verbindung oder mit Serverfehler (Status 500 und höher),
//  - abgelaufene Anmeldung (Abruf landet auf /login),
//  - keine Internetverbindung (dauerhafter Hinweis bis die Verbindung
//    wieder da ist; Formulare werden dann gar nicht erst abgeschickt).
// Seitenaufrufe ohne Verbindung fängt der Service Worker ab (public/sw.js,
// Ersatzseite public/offline.html).
//
// Für eigene Skripte: window.showLoadError('Text', { reload: true }).
(function () {
  'use strict';

  if (window.showLoadError) {
    return;
  }

  var MESSAGES = {
    network: 'Keine Verbindung zum Server. Bitte Internetverbindung prüfen und erneut versuchen.',
    server: 'Der Server hat einen Fehler gemeldet – die Daten konnten nicht geladen werden.',
    session: 'Deine Anmeldung ist abgelaufen. Bitte melde dich erneut an.',
    offline: 'Keine Internetverbindung – Änderungen können gerade nicht gespeichert werden.',
    offlineSubmit: 'Keine Internetverbindung – das Formular wurde nicht abgeschickt. Bitte erneut versuchen, sobald die Verbindung wieder da ist.',
  };

  var unloading = false;
  var unloadTimer = null;
  var recent = {};
  var stack = null;
  var offlineToast = null;

  function markUnloading() {
    // Beim Verlassen der Seite abgebrochene Abrufe sind keine Fehler. Bei
    // Downloads bleibt die Seite stehen, deshalb nach kurzer Zeit zurücksetzen.
    unloading = true;
    clearTimeout(unloadTimer);
    unloadTimer = setTimeout(function () { unloading = false; }, 3000);
  }
  window.addEventListener('beforeunload', markUnloading);
  window.addEventListener('pagehide', markUnloading);
  window.addEventListener('pageshow', function () { unloading = false; });

  // ---- Darstellung ----------------------------------------------------

  function injectStyles() {
    if (document.getElementById('load-error-styles')) {
      return;
    }
    var style = document.createElement('style');
    style.id = 'load-error-styles';
    style.textContent =
      '.le-stack{position:fixed;left:0;right:0;bottom:0;z-index:2147483000;display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:0 12px calc(12px + env(safe-area-inset-bottom));pointer-events:none}' +
      '.le-toast{pointer-events:auto;display:flex;align-items:flex-start;gap:.75rem;width:100%;max-width:32rem;padding:.75rem .875rem;border-radius:.625rem;border:1px solid #fecaca;background:#fef2f2;color:#7f1d1d;box-shadow:0 10px 25px -5px rgba(15,23,42,.25);font:500 .875rem/1.35 system-ui,-apple-system,"Segoe UI",sans-serif;animation:le-in .18s ease-out}' +
      '.le-toast.le-warn{border-color:#fde68a;background:#fffbeb;color:#78350f}' +
      '.le-icon{flex:none;width:1.25rem;height:1.25rem;margin-top:.05rem}' +
      '.le-text{flex:1;min-width:0}' +
      '.le-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem}' +
      '.le-btn{border:1px solid currentColor;border-radius:.375rem;background:transparent;color:inherit;padding:.25rem .625rem;font:inherit;font-size:.8125rem;cursor:pointer;text-decoration:none}' +
      '.le-btn:hover{background:rgba(255,255,255,.5)}' +
      '.le-close{flex:none;border:0;background:transparent;color:inherit;opacity:.7;cursor:pointer;font-size:1.25rem;line-height:1;padding:0 .125rem}' +
      '.le-close:hover{opacity:1}' +
      'html.dark .le-toast{border-color:#7f1d1d;background:#450a0a;color:#fecaca}' +
      'html.dark .le-toast.le-warn{border-color:#78350f;background:#451a03;color:#fde68a}' +
      'html.dark .le-btn:hover{background:rgba(0,0,0,.25)}' +
      '@keyframes le-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}' +
      '@media (prefers-reduced-motion:reduce){.le-toast{animation:none}}';
    document.head.appendChild(style);
  }

  function container() {
    if (stack && document.body.contains(stack)) {
      return stack;
    }
    injectStyles();
    stack = document.createElement('div');
    stack.className = 'le-stack';
    stack.setAttribute('role', 'region');
    stack.setAttribute('aria-label', 'Meldungen');
    document.body.appendChild(stack);
    return stack;
  }

  var ICON_ERROR = '<svg class="le-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>';
  var ICON_CLOCK = '<svg class="le-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .2.08.39.22.53l3 3a.75.75 0 1 0 1.06-1.06l-2.78-2.78V5Z" clip-rule="evenodd"/></svg>';
  var ICON_OFFLINE = '<svg class="le-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.22 2.22a.75.75 0 0 1 1.06 0l14.5 14.5a.75.75 0 1 1-1.06 1.06l-3.1-3.1a3.5 3.5 0 0 0-4.62-.62l-1.05-1.05a5 5 0 0 1 3.66-.16L9.9 11.1a6.5 6.5 0 0 0-4.31 1.95.75.75 0 0 1-1.06-1.06 8 8 0 0 1 3.4-2.07L6.3 8.27A9.5 9.5 0 0 0 2.53 10.6a.75.75 0 1 1-1.06-1.06 11 11 0 0 1 3.71-2.4L2.22 3.28a.75.75 0 0 1 0-1.06ZM10 5.5c-.5 0-.99.03-1.47.1L7.2 4.27A11 11 0 0 1 18.53 9.54a.75.75 0 0 1-1.06 1.06A9.5 9.5 0 0 0 10 5.5Zm0 9a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/></svg>';

  /**
   * Meldung anzeigen. options: reload (Button "Neu laden"), login (Button
   * "Anmelden"), actions ([{label, onClick(toast)}]), sticky (bleibt bis zum
   * Schließen), warn (gelb statt rot), icon ('clock').
   */
  function show(message, options) {
    options = options || {};
    if (!document.body) {
      document.addEventListener('DOMContentLoaded', function () { show(message, options); });
      return null;
    }
    // Dieselbe Meldung nicht mehrfach übereinander stapeln.
    var now = Date.now();
    if (recent[message] && now - recent[message].time < 4000 && document.body.contains(recent[message].el)) {
      return recent[message].el;
    }

    var toast = document.createElement('div');
    toast.className = 'le-toast' + (options.warn ? ' le-warn' : '');
    toast.setAttribute('role', 'alert');
    toast.innerHTML = (options.icon === 'clock' ? ICON_CLOCK : (options.warn ? ICON_OFFLINE : ICON_ERROR)) + '<div class="le-text"></div>';
    toast.querySelector('.le-text').textContent = message;

    var actions = document.createElement('div');
    actions.className = 'le-actions';
    if (options.reload) {
      var reload = document.createElement('button');
      reload.type = 'button';
      reload.className = 'le-btn';
      reload.textContent = 'Seite neu laden';
      reload.addEventListener('click', function () { window.location.reload(); });
      actions.appendChild(reload);
    }
    if (options.login) {
      var login = document.createElement('a');
      login.className = 'le-btn';
      login.href = '/login';
      login.textContent = 'Anmelden';
      actions.appendChild(login);
    }
    (options.actions || []).forEach(function (action) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'le-btn';
      button.textContent = action.label;
      button.addEventListener('click', function () { action.onClick(toast); });
      actions.appendChild(button);
    });
    if (actions.children.length) {
      toast.querySelector('.le-text').appendChild(actions);
    }

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'le-close';
    close.setAttribute('aria-label', 'Meldung schließen');
    close.innerHTML = '&times;';
    close.addEventListener('click', function () { remove(toast); });
    toast.appendChild(close);

    container().appendChild(toast);
    recent[message] = { time: now, el: toast };
    if (!options.sticky) {
      setTimeout(function () { remove(toast); }, 10000);
    }
    return toast;
  }

  function remove(toast) {
    if (toast && toast.parentNode) {
      toast.parentNode.removeChild(toast);
    }
  }

  window.showLoadError = show;
  window.hideLoadError = remove;

  // ---- Abrufe überwachen ------------------------------------------------

  function sameOrigin(url) {
    try {
      return new URL(url, window.location.href).origin === window.location.origin;
    } catch (e) {
      return false;
    }
  }

  function checkResponse(status, responseUrl, redirected) {
    if (unloading) {
      return;
    }
    if (redirected && responseUrl && sameOrigin(responseUrl) && new URL(responseUrl).pathname === '/login' && window.location.pathname !== '/login') {
      show(MESSAGES.session, { login: true, sticky: true });
    } else if (status >= 500) {
      show(MESSAGES.server + ' (Fehler ' + status + ')', { reload: true });
    }
  }

  function networkFailed() {
    if (unloading) {
      return;
    }
    if (navigator.onLine === false) {
      showOffline();
    } else {
      show(MESSAGES.network, { reload: true });
    }
  }

  if (window.fetch) {
    var originalFetch = window.fetch;
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || String(input);
      var watched = sameOrigin(url) && !(init && init.headers && (init.headers['X-No-Load-Error'] || (typeof init.headers.get === 'function' && init.headers.get('X-No-Load-Error'))));
      return originalFetch.apply(this, arguments).then(function (response) {
        if (watched) {
          checkResponse(response.status, response.url, response.redirected);
        }
        return response;
      }, function (error) {
        if (watched && !(error && error.name === 'AbortError')) {
          networkFailed();
        }
        throw error;
      });
    };
  }

  if (window.XMLHttpRequest) {
    var originalOpen = XMLHttpRequest.prototype.open;
    var originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) {
      this.__leWatched = sameOrigin(url);
      return originalOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () {
      if (this.__leWatched) {
        var xhr = this;
        xhr.addEventListener('load', function () { checkResponse(xhr.status, xhr.responseURL, false); });
        xhr.addEventListener('error', networkFailed);
      }
      return originalSend.apply(this, arguments);
    };
  }

  // ---- Offline -------------------------------------------------------------

  function showOffline() {
    if (offlineToast && document.body && document.body.contains(offlineToast)) {
      return;
    }
    offlineToast = show(MESSAGES.offline, { sticky: true, warn: true });
  }

  window.addEventListener('offline', showOffline);
  window.addEventListener('online', function () {
    remove(offlineToast);
    offlineToast = null;
  });
  if (navigator.onLine === false) {
    showOffline();
  }

  // Ohne Verbindung Formulare gar nicht erst abschicken (sonst bleibt die
  // App ohne Rückmeldung stehen bzw. zeigt eine leere Seite). Läuft vor der
  // Lösch-Rückfrage (Capture-Phase auf window).
  window.addEventListener('submit', function (e) {
    if (navigator.onLine === false) {
      e.preventDefault();
      e.stopImmediatePropagation();
      show(MESSAGES.offlineSubmit, { warn: true });
    }
  }, true);
})();
