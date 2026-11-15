# Ablauf-Schnittstelle für Stagedisplays (Run-Sheet-API)

Stand: Migration 158 „Ablauf am FOH“. Diese Beschreibung ist die Vorlage für ein separat entwickeltes Stagedisplay (Bühnenmonitor, Konfidenzmonitor, Countdown-Anzeige o. Ä.). Dieselben Daten zeigt die FOH-Oberfläche **Ablauf am FOH** (`/minutenprogramme/{id}/foh`).

## Überblick

- Ein **Minutenprogramm** besteht aus Programmpunkten mit Dauer. Die geplante Startzeit eines Punkts ergibt sich aus dem Programmbeginn plus der Summe der Dauern davor.
- Am FOH wird der **Verzug** gepflegt – entweder als exakter Wert (z. B. `+4:30`) oder indem bei einem Programmpunkt **Start** gedrückt wird. Dann gilt: Verzug = tatsächlicher Start − geplanter Start.
- Nach einem Start bleibt dieser Punkt **aktuell**, bis der nächste gestartet wird („FOH-Modus“, `live.tracking = true`). Überzieht er, wächst der **prognostizierte Verzug** (`live.projectedDelaySeconds`) sekündlich mit, ohne dass jemand etwas eingeben muss.
- Ohne Start gilt der „Uhr-Modus“: aktuell ist der letzte Punkt, dessen verschobene Startzeit erreicht ist.
- **Programm beenden** markiert das Programm als beendet (`live.state = "finished"`), **Zurücksetzen** stellt den ursprünglichen Plan wieder her.

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
  "event": { "name": "Firmengala 2026" },
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
      "durationMinutes": 10,
      "status": "done",
      "plannedStart": "…", "plannedEnd": "…",
      "currentStart": "…",
      "projectedStart": "…", "projectedEnd": "…",
      "actualStart": null
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
| `notes` | Hinweise je Gewerk: `general`, `sound`, `light`, `video`, `stage` (jeweils `null`, wenn leer). |

Alle Zeitangaben sind ISO 8601 mit Zeitzonen-Offset. Innerhalb von `apiVersion` 1 kommen nur neue Felder hinzu; Clients müssen unbekannte Felder ignorieren.

## Empfehlungen für das Stagedisplay

- Alle **2–5 s** `GET` abfragen. Countdowns lokal jede Sekunde aus `startsAt`/`endsAt` und dem Uhren-Offset berechnen – nicht auf die nächste Antwort warten.
- Im FOH-Modus (`live.tracking`) den Verzug lokal fortschreiben: Ist der nächste Punkt überfällig, gilt `projectedDelaySeconds ≈ delaySeconds + (jetzt − next.currentStart)` (Wert `currentStart` aus `items`).
- Bei Netzwerkfehlern den letzten Stand weiter anzeigen und sichtbar als „offline“ markieren.
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
