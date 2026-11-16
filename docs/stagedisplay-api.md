# Ablauf-Schnittstelle für Stagedisplays (Run-Sheet-API)

Stand: Migration 158 „Ablauf am FOH“. Diese Beschreibung ist die Vorlage für ein separat entwickeltes Stagedisplay (Bühnenmonitor, Konfidenzmonitor, Countdown-Anzeige o. Ä.). Dieselben Daten zeigt die FOH-Oberfläche **Ablauf am FOH** (`/minutenprogramme/{id}/foh`).

> Für fest installierte Displays mit eigenem Geräte-Token, zentraler Zuordnung und Rolle (Stagedisplay/FOH-Display) gibt es seit Migration 168 die Display-Schnittstelle, siehe [display-api.md](display-api.md). Sie liefert die Ablaufdaten im selben Format.

## Überblick

- Ein **Minutenprogramm** besteht aus Programmpunkten mit Dauer. Die geplante Startzeit eines Punkts ergibt sich aus dem Programmbeginn plus der Summe der Dauern davor.
- Am FOH wird der **Verzug** gepflegt – entweder als exakter Wert (z. B. `+4:30`) oder indem bei einem Programmpunkt **Start** gedrückt wird. Dann gilt: Verzug = tatsächlicher Start − geplanter Start.
- Nach einem Start bleibt dieser Punkt **aktuell**, bis der nächste gestartet wird („FOH-Modus“, `live.tracking = true`). Überzieht er, wächst der **prognostizierte Verzug** (`live.projectedDelaySeconds`) sekündlich mit, ohne dass jemand etwas eingeben muss.
- Ohne Start gilt der „Uhr-Modus“: aktuell ist der letzte Punkt, dessen verschobene Startzeit erreicht ist.
- **Programm beenden** markiert das Programm als beendet (`live.state = "finished"`), **Zurücksetzen** stellt den ursprünglichen Plan wieder her.
- **Nachrichten** (Migration 167): Der Veranstalter kann über die Live-Oberfläche „Audio & Durchsagen“ (`/live/{id}`) kurze Texte an das Stagedisplay und/oder den FOH schicken. Das Stagedisplay erhält in `messages` alle aktiven Nachrichten, die an die Bühne gerichtet sind. Eine Nachricht bleibt aktiv, bis der Absender sie beendet oder der FOH sie ausblendet – dann fehlt sie in der nächsten Antwort.

## Zugang (Token)

Zugang ist der Token eines **Freigabe-Links** des Minutenprogramms (Minutenprogramm › Freigabe-Links):

| Link-Modus           | Lesen | Steuern (Verzug, Start, Ende, Zurücksetzen) |
|----------------------|:-----:|:-------------------------------------------:|
| Nur lesen            |   ✓   |                      –                      |
| Lesen & bearbeiten   |   ✓   |                      ✓                      |

Für ein reines Anzeigegerät einen Link **„Nur lesen“** verwenden. Wird der Link widerrufen, ist der Zugang sofort gesperrt (`404`).

Der Token steht im Pfad. Es gibt weder Cookies noch CSRF-Schutz. Alle Antworten senden `Access-Control-Allow-Origin: *`, eine Browser-Anwendung auf einer anderen Domain kann die API also direkt abfragen. Alle Pfade sind relativ zur Basis-URL der Verwaltung, z. B. `https://verleih.example.de`.

## Endpunkte

| Methode | Pfad                                              | Zweck                         | Modus              |
|---------|---------------------------------------------------|-------------------------------|--------------------|
| GET     | `/api/runsheet/v1/{token}`                        | kompletter Live-Stand         | lesen              |
| POST    | `/api/runsheet/v1/{token}/delay`                  | Verzug setzen/ändern          | lesen & bearbeiten |
| POST    | `/api/runsheet/v1/{token}/items/{itemId}/start`   | Programmpunkt starten         | lesen & bearbeiten |
| POST    | `/api/runsheet/v1/{token}/finish`                 | Programm beenden              | lesen & bearbeiten |
| POST    | `/api/runsheet/v1/{token}/reset`                  | Live-Stand zurücksetzen       | lesen & bearbeiten |

POST-Daten dürfen als JSON (`Content-Type: application/json`) oder als Formular (`application/x-www-form-urlencoded`) gesendet werden. Jede erfolgreiche POST-Antwort enthält `"ok": true` und zusätzlich den kompletten neuen Live-Stand (gleiches Format wie `GET`).

### `POST …/delay`

Genau eine der folgenden Angaben (Vorrang in dieser Reihenfolge):

| Feld             | Beispiel   | Bedeutung                                                         |
|------------------|------------|-------------------------------------------------------------------|
| `adopt`          | `true`     | den prognostizierten Verzug (inkl. laufender Überziehung) übernehmen |
| `adjust_seconds` | `60`, `-300` | relativ zum aktuellen Verzug ändern                            |
| `delay_seconds`  | `270`      | absoluter Verzug in Sekunden (negativ = Vorsprung)                |
| `delay`          | `"4:30"`   | absoluter Verzug als Text: Minuten (`5`, `-2`, `1,5`), `mm:ss` oder `h:mm:ss` |

Maximal ±24 Stunden.

### `POST …/items/{itemId}/start`

Ohne Daten gilt „jetzt“. Optional `started_at`, wenn der Punkt schon früher begonnen hat:
`"20:13:20"` (Uhrzeit, wird dem Tag zugeordnet, der der geplanten Zeit am nächsten liegt) oder ein ISO-8601-Zeitstempel.

### Fehler

`{"ok": false, "error": "…"}` mit

- `403` – Link erlaubt nur Lesen,
- `404` – unbekannter/widerrufener Token oder unbekannter Programmpunkt,
- `422` – ungültige Eingabe (z. B. Verzug nicht lesbar).

## Antwort von `GET /api/runsheet/v1/{token}`

```json
{
  "apiVersion": 1,
  "serverTime": "2026-11-15T20:12:00+01:00",
  "revision": "bd7c8561a5b09953",
  "event": { "name": "Firmengala 2026", "noStrobe": false },
  "program": {
    "id": "…", "name": "Abendprogramm", "room": "Großer Saal",
    "plannedStart": "2026-11-15T20:00:00+01:00",
    "currentStart": "2026-11-15T20:01:00+01:00",
    "plannedEnd": "2026-11-15T21:13:00+01:00",
    "projectedEnd": "2026-11-15T21:14:00+01:00",
    "jingleBefore": true, "jingleTitle": "Opener",
    "handheldMicCount": 4, "headsetMicCount": 2,
    "itemCount": 5
  },
  "trades": [
    { "id": "…", "name": "Ton", "color": "#3b82f6" },
    { "id": "…", "name": "Licht", "color": "#f59e0b" },
    { "id": "…", "name": "Rigging", "color": "#a855f7" }
  ],
  "live": {
    "state": "running",
    "tracking": true,
    "delaySeconds": 60,
    "projectedDelaySeconds": 60,
    "overrunSeconds": 0,
    "finishedAt": null,
    "canControl": false
  },
  "current": {
    "id": "…", "position": 2, "name": "Begrüßung", "description": "…",
    "notes": { "general": "…", "sound": "HH 1", "light": "…", "video": null, "stage": null },
    "tradeNotes": [
      { "tradeId": "…", "trade": "Ton", "color": "#3b82f6", "text": "HH 1" },
      { "tradeId": "…", "trade": "Licht", "color": "#f59e0b", "text": "…" },
      { "tradeId": "…", "trade": "Rigging", "color": "#a855f7", "text": "Banner fährt ein" }
    ],
    "noStrobe": true,
    "durationSeconds": 300,
    "startsAt": "2026-11-15T20:11:00+01:00",
    "endsAt": "2026-11-15T20:16:00+01:00",
    "elapsedSeconds": 60,
    "remainingSeconds": 240,
    "actualStart": "2026-11-15T20:11:00+01:00"
  },
  "next": { "…": "gleiches Format wie current" },
  "items": [
    {
      "id": "…", "position": 1, "name": "Einlass", "description": null,
      "notes": { "general": null, "sound": "…", "light": "…", "video": null, "stage": null },
      "tradeNotes": [ { "tradeId": "…", "trade": "Ton", "color": "#3b82f6", "text": "…" } ],
      "noStrobe": false,
      "durationMinutes": 10,
      "status": "done",
      "plannedStart": "…", "plannedEnd": "…",
      "currentStart": "…",
      "projectedStart": "…", "projectedEnd": "…",
      "actualStart": null
    }
  ],
  "messages": [
    {
      "id": "…",
      "text": "Redner kommt 5 Minuten später",
      "urgent": false,
      "stage": true, "foh": true,
      "allPrograms": true,
      "sentAt": "2026-11-15T20:09:30+01:00",
      "sentBy": "Max Muster (Veranstalter)",
      "acknowledgedAt": null, "acknowledgedBy": null
    }
  ]
}
```

### Felder

| Feld | Bedeutung |
|------|-----------|
| `serverTime` | Serverzeit der Antwort. Zum Ausgleich der Geräteuhr: `offset = serverTime − lokale Zeit beim Empfang`. |
| `revision` | Ändert sich, sobald sich Plan oder Live-Stand ändern (nicht mit der Uhrzeit). Für „nur bei Änderung neu zeichnen“. |
| `live.state` | `pending` (vor Beginn), `running`, `finished`. |
| `live.tracking` | `true`: aktueller Punkt wurde per Start gesetzt und bleibt aktuell, bis der nächste startet. `false`: Uhr-Modus. |
| `live.delaySeconds` | Gesetzter Verzug (negativ = Vorsprung). |
| `live.projectedDelaySeconds` | Verzug inklusive laufender Überziehung des aktuellen Punkts. **Das ist der Wert für die Anzeige.** |
| `live.overrunSeconds` | Wie weit der aktuelle Punkt über die geplante Startzeit des nächsten hinaus läuft. |
| `live.canControl` | Token darf steuern. |
| `current` / `next` | Aktueller bzw. nächster Punkt oder `null`. `startsAt` = tatsächlicher bzw. voraussichtlicher Beginn, `endsAt` = Beginn + Dauer (`null` ohne Dauer). `elapsedSeconds` ist beim nächsten Punkt negativ (= Zeit bis zum Start). `remainingSeconds < 0` = überzogen. |
| `items[].status` | `done`, `current`, `next`, `upcoming`. |
| `items[].plannedStart` | Ursprünglicher Plan ohne Verzug. |
| `items[].currentStart` | Plan + gesetzter Verzug. |
| `items[].projectedStart` | Tatsächlicher Beginn (falls gestartet), sonst voraussichtlicher Beginn inkl. laufender Überziehung. |
| `trades` | Gewerke, für die das Minutenprogramm Hinweise führt (festgelegt oder automatisch aus Veranstaltung/Technikern), in der Reihenfolge der Einstellungen. `color` ist eine feste Farbe je Gewerk (`#rrggbb`). |
| `tradeNotes` | Hinweise je Gewerk (bei `current`, `next`, `items[]`): nur nicht-leere Hinweise der Gewerke aus `trades`, in deren Reihenfolge. **Für neue Displays verwenden.** |
| `notes` | `general`: allgemeiner Hinweis. `sound`, `light`, `video`, `stage`: Kompatibilitätsfelder aus der Zeit fester Gewerke – gefüllt mit dem Hinweis des Gewerks namens „Ton“, „Licht“, „Video“ bzw. „Bühne“ (sonst `null`). Hinweise anderer Gewerke stehen nur in `tradeNotes`. |
| `event.noStrobe` | `true`: Bei der gesamten Veranstaltung darf kein Stroboskop-Licht eingesetzt werden. |
| `noStrobe` (bei `current`, `next`, `items[]`) | `true`: Bei diesem Programmpunkt darf kein Stroboskop-Licht eingesetzt werden. Gilt zusätzlich zu `event.noStrobe` – ein Display sollte den Hinweis zeigen, wenn eines der beiden `true` ist. |
| `messages` | Aktive Nachrichten des Veranstalters an die Bühne, älteste zuerst (leeres Array, wenn keine). Über die API kommen nur Nachrichten mit `stage = true`; reine FOH-Nachrichten erscheinen nur auf der FOH-Seite. |
| `messages[].text` | Nachrichtentext (max. 500 Zeichen, kann Zeilenumbrüche enthalten). |
| `messages[].urgent` | Als dringend markiert – auffällig darstellen (z. B. rot, blinkend). |
| `messages[].stage` / `foh` | Ziele, an die die Nachricht geschickt wurde. |
| `messages[].allPrograms` | `true`: gilt für alle Minutenprogramme der Veranstaltung, `false`: nur für dieses. |
| `messages[].acknowledgedAt` / `acknowledgedBy` | Wann/von wem der FOH die Nachricht als gesehen markiert hat (sonst `null`). Sie bleibt trotzdem aktiv. |

Alle Zeitangaben sind ISO 8601 mit Zeitzonen-Offset. Innerhalb von `apiVersion` 1 kommen nur neue Felder hinzu; Clients müssen unbekannte Felder ignorieren.

## Empfehlungen für das Stagedisplay

- Alle **2–5 s** `GET` abfragen. Countdowns lokal jede Sekunde aus `startsAt`/`endsAt` und dem Uhren-Offset berechnen – nicht auf die nächste Antwort warten.
- Im FOH-Modus (`live.tracking`) den Verzug lokal fortschreiben: Ist der nächste Punkt überfällig, gilt `projectedDelaySeconds ≈ delaySeconds + (jetzt − next.currentStart)` (Wert `currentStart` aus `items`).
- Bei Netzwerkfehlern den letzten Stand weiter anzeigen und sichtbar als „offline“ markieren.
- Nachrichten (`messages`) gut lesbar über bzw. neben dem Countdown einblenden; neue Nachrichten (unbekannte `id`) kurz hervorheben. Fehlt eine `id` in einer späteren Antwort, wurde sie beendet und ist auszublenden.
- Beispiel (Browser):

```js
const BASE = 'https://verleih.example.de/api/runsheet/v1/<token>';
let state, offset = 0;
async function poll() {
  const sent = Date.now();
  state = await (await fetch(BASE)).json();
  offset = Date.parse(state.serverTime) - (sent + Date.now()) / 2;
}
function remaining() {
  return state?.current?.endsAt ? (Date.parse(state.current.endsAt) - (Date.now() + offset)) / 1000 : null;
}
setInterval(poll, 3000); poll();
```
