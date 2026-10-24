<?php
// PWA-Grundausstattung: Manifest, Icons, theme-color sowie die Registrierung
// des Service Workers (public/sw.js). Eingebunden sowohl von layout.php als
// auch von den eigenständigen auth/*-Seiten (login.php, two-factor.php,
// forgot-password.php, setup-password.php), die layout.php NICHT einbinden
// — siehe den entsprechenden Kommentar dort zum Theme-Skript, hier gilt
// dasselbe: eine PWA muss bereits auf dem Login-Bildschirm installierbar
// sein, nicht erst nach der Anmeldung.
//
// Bewusst rein statisch (keine Firmenbranding-Daten aus der Datenbank):
// Name, Farben und Icons kommen direkt aus public/manifest.webmanifest und
// public/assets/img/icons/ (siehe README, Abschnitt "Setup" /
// public/assets/img/icons/README.md für den Hinweis, wie sie bei Bedarf
// gegen ein eigenes Firmenlogo ausgetauscht werden können).
?>
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#2563eb">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/img/icons/icon-192.png">
<link rel="apple-touch-icon" href="/assets/img/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Materialverleih">
<script>
  // Globale Registrierung des Service Workers für Installierbarkeit und
  // Offline-Verfügbarkeit der statischen Dateien (siehe public/sw.js). Die
  // Registrierung in resources/views/profile/display.php bleibt zusätzlich
  // bestehen (dort wird derselbe Worker vor dem Abonnieren von
  // Push-Benachrichtigungen erneut registriert — register() mit identischer
  // Worker-URL ist ohne Nebenwirkungen wiederholbar, der Browser liefert
  // einfach die bereits laufende Registrierung zurück).
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {
        // Registrierung fehlgeschlagen (z. B. Seite wird nicht über HTTPS
        // bzw. localhost ausgeliefert) — die Anwendung funktioniert auch
        // ohne Service Worker vollständig, daher kein Fehler-UI nötig.
      });
    });
  }
</script>
