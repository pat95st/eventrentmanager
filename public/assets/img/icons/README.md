# PWA-Icons

Diese Dateien werden ausschließlich von `public/manifest.webmanifest` sowie
vom Service Worker (`public/sw.js`, für das Icon/Badge einer
Push-Benachrichtigung) referenziert.

Es handelt sich um einen generischen Platzhalter (Buchstabe „M“ auf
`#2563eb`, dem Standard-Markenblau aus `resources/css/app.css`). Für ein
eigenes Firmenlogo einfach diese sechs Dateien durch eigene PNGs mit
identischem Namen und identischer Größe ersetzen:

| Datei                     | Größe   | Verwendung                                   |
|---------------------------|---------|-----------------------------------------------|
| `icon-192.png`             | 192×192 | App-Icon (Startbildschirm, Task-Switcher)     |
| `icon-512.png`              | 512×512 | App-Icon (Splash-Screen, Store-Darstellung)   |
| `icon-maskable-512.png`     | 512×512 | „Maskable“: **randlos**, Motiv nur in der mittleren ~80 %-Zone, da das Betriebssystem den Rand selbst zuschneidet (Kreis/Rundraute/…) |
| `apple-touch-icon.png`      | 180×180 | iOS-Homescreen-Icon, ebenfalls randlos (iOS rundet selbst) |
| `favicon-32.png` / `favicon-16.png` | 32×32 / 16×16 | Browser-Tab-Icon |

Nach dem Austausch zusätzlich `public/favicon.ico` neu erzeugen (oder
weglassen — `manifest.webmanifest` und die `<link rel="icon">`-Tags in
`resources/views/_pwa_head.php` greifen auch ohne .ico), z. B.:

```bash
convert assets/img/icons/favicon-16.png assets/img/icons/favicon-32.png favicon.ico
```

Die `CACHE_VERSION` in `public/sw.js` erhöhen, damit bereits installierte
PWAs die neuen Icons zeitnah übernehmen (siehe Kommentar dort).
