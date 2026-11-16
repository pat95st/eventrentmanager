# Display-Schnittstelle für Stage- und FOH-Displays

Stand: Migration 168 „Stage- & FOH-Displays“. Diese Beschreibung ist die Vorlage für Display-Clients (Bühnenmonitor, Konfidenzmonitor, Regie-Bildschirm). Eine fertige Umsetzung im Browser liefert die Verwaltung selbst unter `/display` mit.

## Überblick

- Displays werden unter **Einstellungen › Veranstaltungen & Planung › Stage- & FOH-Displays** angelegt. Jedes Display hat einen eigenen **Geräte-Token** (`mvd_…`), der nur beim Anlegen bzw. Neu-Erzeugen einmal angezeigt wird.
- Jedes Display hat eine **Rolle**: `stage` (Stagedisplay) oder `foh` (FOH-Display). Die Rolle lässt sich jederzeit umstellen, ohne dass sich am Gerät etwas ändert. **Ein Client soll daher beide Darstellungen beherrschen** und anhand von `display.role` und `display.options` entscheiden, was er zeigt.
- Welches **Minutenprogramm** ein Display zeigt, wird zentral festgelegt:
  1. ein fest gewähltes Minutenprogramm, sonst
  2. das jeweils laufende Programm einer gewählten Veranstaltung, sonst
  3. das jeweils laufende Programm aller heute laufenden Veranstaltungen (Vortag des Beginns bis Tag nach dem Ende).

  Bei 2. und 3. kann zusätzlich auf einen **Raum** eingeschränkt werden. Gewählt wird ein laufendes Programm (am FOH gestartet oder zwischen Beginn und voraussichtlichem Ende), sonst das nächste anstehende, sonst das zuletzt begonnene.
- Die Ablaufdaten (`runSheet`) haben **genau dasselbe Format** wie die Run-Sheet-API (`/api/runsheet/v1/{token}`, siehe [stagedisplay-api.md](stagedisplay-api.md)). Ein vorhandener Client für die Run-Sheet-API lässt sich also leicht umstellen.

## Authentifizierung

Jede Anfrage sendet `Authorization: Bearer <Display-Token>`. Bei ungültigem Token oder deaktiviertem Display antwortet der Server mit `401` und `{"ok": false, "error": "…"}`.

Es gibt weder Cookies noch CSRF-Schutz. Alle Antworten senden `Access-Control-Allow-Origin: *` (inkl. `Authorization` in den erlaubten Headern), ein Browser-Client auf einer anderen Domain kann die API also direkt abfragen. Alle Pfade sind relativ zur Basis-URL der Verwaltung, z. B. `https://verleih.example.de`.

## Endpunkte

| Methode | Pfad                                         | Zweck                          | Erlaubt                          |
|---------|----------------------------------------------|--------------------------------|----------------------------------|
| GET     | `/api/display/v1/state?version=<client>`     | Konfiguration + Live-Stand     | jedes Display                    |
| POST    | `/api/display/v1/delay`                      | Verzug setzen/ändern           | FOH-Display mit „Steuern erlauben“ |
| POST    | `/api/display/v1/items/{itemId}/start`       | Programmpunkt starten          | FOH-Display mit „Steuern erlauben“ |
| POST    | `/api/display/v1/finish`                     | Programm beenden               | FOH-Display mit „Steuern erlauben“ |
| POST    | `/api/display/v1/reset`                      | Live-Stand zurücksetzen        | FOH-Display mit „Steuern erlauben“ |
| POST    | `/api/display/v1/messages/{id}/seen`         | Nachricht als gesehen markieren | FOH-Display mit „Steuern erlauben“ |
| POST    | `/api/display/v1/messages/{id}/hide`         | Nachricht ausblenden (auch auf der Bühne) | FOH-Display mit „Steuern erlauben“ |

- Jede Abfrage von `state` zählt als Lebenszeichen; nach 45 s ohne Abfrage gilt das Display in der Verwaltung als offline. `version` (optional) erscheint in der Geräteübersicht.
- Die POST-Endpunkte nehmen dieselben Daten wie die Run-Sheet-API (`adopt`, `adjust_seconds`, `delay_seconds`, `delay` bzw. `started_at`), als JSON oder Formular. Sie wirken auf das Programm, das das Display gerade zeigt. Die Antwort enthält `"ok": true` und den kompletten neuen Stand (wie `GET …/state`).

Fehler: `401` ungültiger Token, `403` Display darf nicht steuern, `404` unbekannter Programmpunkt, `409` dem Display ist gerade kein Minutenprogramm zugeordnet, `422` ungültige Eingabe.

## Antwort von `GET /api/display/v1/state`

```json
{
  "apiVersion": 1,
  "serverTime": "2026-11-15T20:12:00+01:00",
  "pollIntervalSeconds": 3,
  "display": {
    "id": "…",
    "name": "Bühnenmonitor links",
    "role": "stage",
    "roleLabel": "Stagedisplay",
    "canControl": false,
    "assignment": { "mode": "rental", "rentalId": "…", "minuteProgramId": null, "room": "Großer Saal" },
    "options": {
      "notes": ["general", "stage"],
      "showClock": true,
      "showDelay": true,
      "showNext": true,
      "showItemList": false,
      "showDescription": true,
      "showMics": false,
      "allowControl": false,
      "theme": "dark",
      "fontScale": 100
    },
    "thresholds": { "warnSeconds": 120, "criticalSeconds": 30 },
    "configRevision": "4f0c2a9e1b7d3c55"
  },
  "runSheet": { "…": "Format wie GET /api/runsheet/v1/{token}, oder null" }
}
```

### Felder

| Feld | Bedeutung |
|------|-----------|
| `pollIntervalSeconds` | Empfohlener Abstand der Abfragen (Einstellung „Abfrageintervall“). |
| `display.role` | `stage` oder `foh`. Bestimmt die Darstellung. |
| `display.canControl` | `true` nur bei FOH-Displays mit „Steuern erlauben“. Nur dann Steuer-Elemente zeigen. Entspricht `runSheet.live.canControl`. |
| `display.assignment.mode` | `program` (fest gewähltes Programm), `rental` (laufendes Programm einer Veranstaltung) oder `auto`. Nur zur Information. |
| `display.options.notes` | Gewerke, deren Hinweise angezeigt werden sollen: `general`, `sound`, `light`, `video`, `stage` (Schlüssel von `runSheet.*.notes`). |
| `display.options.show*` | Uhrzeit, Verzug, nächster Punkt, Ablaufliste, Beschreibung, Mikrofonanzahl ein/aus. |
| `display.options.theme` | `dark` oder `light`. |
| `display.options.fontScale` | Schriftgröße in Prozent (50–200). |
| `display.thresholds` | Restzeit in Sekunden, ab der der Countdown gelb (`warnSeconds`) bzw. rot (`criticalSeconds`, sowie beim Überziehen) dargestellt werden soll. |
| `display.configRevision` | Ändert sich, sobald sich die Konfiguration des Displays ändert. Zusammen mit `runSheet.revision` für „nur bei Änderung neu zeichnen“. |
| `runSheet` | Live-Stand des gezeigten Minutenprogramms oder `null`, wenn gerade keins zugeordnet ist (dann z. B. den Display-Namen und „kein Programm“ zeigen). |
| `runSheet.messages` | Aktive Nachrichten des Veranstalters (Format siehe [stagedisplay-api.md](stagedisplay-api.md)). **Stagedisplays** erhalten nur Nachrichten an die Bühne (`stage = true`), **FOH-Displays** alle. Ein FOH-Display mit Steuerung kann sie als gesehen markieren oder ausblenden. |

Innerhalb von `apiVersion` 1 kommen nur neue Felder hinzu; Clients müssen unbekannte Felder (auch unbekannte Optionen) ignorieren.

## Empfehlungen

- Im Abstand von `pollIntervalSeconds` abfragen, Countdowns lokal jede Sekunde aus `startsAt`/`endsAt` und dem Uhren-Offset (`serverTime`) berechnen – siehe die Empfehlungen in [stagedisplay-api.md](stagedisplay-api.md).
- Bei Netzwerkfehlern den letzten Stand weiter anzeigen und sichtbar als „offline“ markieren.
- `event.noStrobe` oder `current.noStrobe` deutlich als „Kein Stroboskop“ anzeigen.
- Nachrichten (`runSheet.messages`) gut sichtbar zeigen, dringende (`urgent`) besonders auffällig.

## Browser-Client `/display`

Die Verwaltung liefert unter `/display` einen fertigen Client (eine Seite, ohne Anmeldung). Beim ersten Aufruf wird der Display-Token eingegeben und im Browser gespeichert. Alternativ den Link `/display#token=mvd_…` öffnen, den die Einstellungsseite nach dem Anlegen anzeigt – der Anker wird nicht an den Server übertragen und danach aus der Adresszeile entfernt. Taste `f` schaltet den Vollbildmodus um. Für Kiosk-Geräte (z. B. Raspberry Pi mit Chromium im Kiosk-Modus) genügt es, diese Adresse als Startseite einzutragen.
