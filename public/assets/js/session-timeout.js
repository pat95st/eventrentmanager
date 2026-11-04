// Hinweis vor der automatischen Abmeldung (Einstellungen › Sicherheit,
// Migration 127). Kurz vor Ablauf erscheint unten eine Meldung mit
// Countdown und „Angemeldet bleiben“; ist die Zeit abgelaufen, wechselt die
// Seite zur Anmeldung (danach geht es auf derselben Seite weiter).
//
// Maßgeblich ist immer der Server (/sitzung/status): Nachladen per AJAX in
// anderen Tabs oder in dieser Seite zählt dort ebenfalls als Aktivität.
(function () {
  'use strict';

  var timeout = parseInt(window.SESSION_TIMEOUT_SECONDS || '0', 10);
  if (!timeout || !window.fetch) {
    return;
  }

  var WARN = Math.max(30, Math.min(120, Math.floor(timeout / 5))); // Sekunden vor Ablauf
  var deadline = Date.now() + timeout * 1000;
  var timer = null;
  var tick = null;
  var toast = null;
  var expired = false;

  function schedule() {
    clearTimeout(timer);
    var wait = Math.max(1000, deadline - WARN * 1000 - Date.now());
    // setTimeout verträgt höchstens ~24 Tage; bei langen Zeiten in Etappen prüfen.
    timer = setTimeout(check, Math.min(wait, 6 * 3600 * 1000));
  }

  function status() {
    var page = window.location.pathname + window.location.search;
    return fetch('/sitzung/status?seite=' + encodeURIComponent(page), {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-No-Load-Error': '1' },
      credentials: 'same-origin',
      cache: 'no-store',
    }).then(function (res) { return res.json(); });
  }

  function check() {
    if (expired) {
      return;
    }
    status().then(function (data) {
      if (!data.loggedIn) {
        expire();
        return;
      }
      deadline = Date.now() + data.remaining * 1000;
      if (data.remaining > WARN + 5) {
        hideWarning();
        schedule();
      } else {
        showWarning();
      }
    }).catch(function () {
      // Server/Netz nicht erreichbar: später erneut versuchen (Meldung kommt von load-errors.js beim nächsten Abruf).
      clearTimeout(timer);
      timer = setTimeout(check, 30000);
    });
  }

  function format(seconds) {
    seconds = Math.max(0, Math.round(seconds));
    var m = Math.floor(seconds / 60);
    var s = seconds % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function text() {
    return 'Du wirst wegen Inaktivität in ' + format((deadline - Date.now()) / 1000) + ' Minuten automatisch abgemeldet.';
  }

  function showWarning() {
    if (!window.showLoadError) {
      return;
    }
    if (!toast || !document.body.contains(toast)) {
      toast = window.showLoadError(text(), {
        sticky: true,
        warn: true,
        icon: 'clock',
        actions: [{ label: 'Angemeldet bleiben', onClick: extend }],
      });
    }
    clearInterval(tick);
    tick = setInterval(function () {
      var left = (deadline - Date.now()) / 1000;
      var label = toast && toast.querySelector('.le-text');
      if (label && label.firstChild) {
        label.firstChild.textContent = text();
      }
      if (left <= 0) {
        clearInterval(tick);
        check(); // Server entscheidet (evtl. war in einem anderen Tab Aktivität)
      }
    }, 1000);
  }

  function hideWarning() {
    clearInterval(tick);
    if (toast && window.hideLoadError) {
      window.hideLoadError(toast);
    }
    toast = null;
  }

  function extend() {
    fetch('/sitzung/verlaengern', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
      .then(function (res) {
        if (res.redirected && new URL(res.url).pathname === '/login') {
          expire();
          return null;
        }
        return res.json();
      })
      .then(function (data) {
        if (data) {
          deadline = Date.now() + data.remaining * 1000;
          hideWarning();
          schedule();
        }
      })
      .catch(function () { /* Meldung zeigt load-errors.js */ });
  }

  function expire() {
    expired = true;
    hideWarning();
    window.location.href = '/login';
  }

  // Nach Ruhezustand/Tab-Wechsel laufen Timer nicht zuverlässig weiter.
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      check();
    }
  });
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      check();
    }
  });

  schedule();
})();
