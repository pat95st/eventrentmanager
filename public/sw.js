// Service Worker für zwei Zwecke:
//
// 1. Web-Push-Benachrichtigungen (siehe src/Infrastructure/Push/
//    WebPushSender.php und resources/views/profile/display.php) —
//    unverändert gegenüber der ursprünglichen, minimalen Fassung.
//
// 2. PWA-Installierbarkeit + Offline-Verfügbarkeit der ECHT statischen
//    Dateien (CSS, JS, Icons, Manifest). Bewusst NICHT gecacht werden:
//    - alle serverseitig gerenderten Seiten (sie enthalten pro Aufruf ein
//      frisches CSRF-Token und immer aktuelle Daten aus der Datenbank —
//      ein gecachtes HTML-Dokument würde mit einem veralteten Token
//      ausgeliefert und dadurch jede zustandsändernde Aktion mit 403
//      scheitern lassen),
//    - alle API-/Datenabrufe,
//    - alle Bilder (Firmenlogo, Hintergrundbild, Artikel-/Beleg-Anhänge
//      usw.) — sie kommen aus der Datenbank bzw. dem Upload-Verzeichnis
//      und sollen laut Vorgabe immer frisch nachgeladen werden, nicht aus
//      dem Cache.
//
// Registriert wird dieser Service Worker global in resources/views/
// layout.php (und den eigenständigen auth/*-Seiten, die layout.php nicht
// einbinden) als echte Datei unter /sw.js (siehe public/.htaccess —
// existierende Dateien werden NICHT an den PHP-Front-Controller
// weitergereicht), damit der Registrierungs-Scope die gesamte Anwendung
// abdeckt.

// Bei Änderungen an einer der unten gelisteten statischen Dateien (z. B.
// nach "npm run build:css") diese Versionsnummer erhöhen — das legt beim
// nächsten Seitenaufruf einen neuen Cache an und räumt in activate() den
// alten auf, statt auf die (im Hintergrund laufende, also nicht sofort
// sichtbare) Stale-while-revalidate-Aktualisierung zu warten.
var CACHE_VERSION = 'static-v1';

var PRECACHE_URLS = [
  '/assets/css/app.css',
  '/assets/js/barcode-scanner.js',
  '/manifest.webmanifest',
  '/favicon.ico',
  '/assets/img/icons/favicon-16.png',
  '/assets/img/icons/favicon-32.png',
  '/assets/img/icons/icon-192.png',
  '/assets/img/icons/icon-512.png',
  '/assets/img/icons/icon-maskable-512.png',
  '/assets/img/icons/apple-touch-icon.png',
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_VERSION).then(function (cache) {
      return cache.addAll(PRECACHE_URLS);
    })
  );
  // Sofort aktiv werden, statt auf das Schließen aller offenen Tabs zu
  // warten — bei einer intern genutzten Verwaltungssoftware ist das
  // unproblematisch und sorgt dafür, dass ein Versions-Bump zügig greift.
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys
          .filter(function (key) { return key !== CACHE_VERSION; })
          .map(function (key) { return caches.delete(key); })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

function isPrecachedAsset(url) {
  if (url.origin !== self.location.origin) {
    return false;
  }
  return PRECACHE_URLS.indexOf(url.pathname) !== -1;
}

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') {
    return; // POST/PUT/DELETE unverändert direkt ans Netzwerk (Formulare, API-Schreibzugriffe).
  }

  var url = new URL(event.request.url);

  if (!isPrecachedAsset(url)) {
    // Alles andere (Seiten, API, Bilder/Uploads) bewusst NICHT abgefangen —
    // der Browser behandelt die Anfrage genauso, als gäbe es diesen Service
    // Worker nicht, und lädt immer frisch vom Server.
    return;
  }

  // Stale-while-revalidate: aus dem Cache sofort antworten (schnell, auch
  // offline verfügbar), im Hintergrund parallel eine frische Version holen
  // und den Cache für den nächsten Aufruf aktualisieren.
  event.respondWith(
    caches.open(CACHE_VERSION).then(function (cache) {
      return cache.match(event.request).then(function (cached) {
        var networkFetch = fetch(event.request).then(function (response) {
          if (response && response.ok) {
            cache.put(event.request, response.clone());
          }
          return response;
        }).catch(function () {
          return cached; // offline und nicht im Cache: nichts weiter zu tun, cached ist ggf. bereits undefined
        });

        return cached || networkFetch;
      });
    })
  );
});

self.addEventListener('push', function (event) {
  var data = { title: 'Materialverleih', body: '', url: '/' };

  if (event.data) {
    try {
      var parsed = event.data.json();
      data.title = parsed.title || data.title;
      data.body = parsed.body || data.body;
      data.url = parsed.url || data.url;
    } catch (e) {
      // Falls die Nutzlast unerwartet kein JSON ist, wird der reine Text
      // als Nachrichtentext verwendet, statt die Benachrichtigung ganz
      // ausfallen zu lassen.
      data.body = event.data.text();
    }
  }

  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/assets/img/icons/icon-192.png',
      badge: '/assets/img/icons/icon-192.png',
      data: { url: data.url },
    })
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();

  var targetUrl = (event.notification.data && event.notification.data.url) || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
      for (var i = 0; i < windowClients.length; i++) {
        var client = windowClients[i];
        if ('focus' in client) {
          client.navigate(targetUrl);
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
