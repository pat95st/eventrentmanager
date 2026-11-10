# Abspielgerät-Schnittstelle (Raspberry Pi am Mischpult)

Stand: Migration 135 „Veranstaltungs-Audio“. Diese Beschreibung ist die Vorlage für die Pi-Software. Die Pi-Software wird separat entwickelt.

## Ablauf

1. In der Verwaltung unter **Einstellungen › Veranstaltungs-Audio** ein Abspielgerät anlegen. Der Geräte-Token (`mvp_…`) wird **nur einmal** angezeigt und muss auf dem Pi hinterlegt werden.
2. In der Veranstaltung unter **Audio & Durchsagen** das Gerät zuordnen.
3. Der Pi fragt alle `poll_interval_seconds` (derzeit 2 s) `GET /api/player/v1/state` ab:
   - Jede Abfrage zählt als Lebenszeichen. Nach 45 s ohne Abfrage gilt das Gerät als offline.
   - Die Antwort enthält die aktive Veranstaltung, alle Audio-Dateien (Manifest), den Gong, die Sprachausgabe-Einstellungen und die Warteschlange.
4. Der Pi hält die Dateien lokal vor:
   - Er lädt neue oder geänderte Dateien herunter, erkennbar am Feld `version`.
   - Dateien, die nicht mehr im Manifest stehen, löscht er.
   - So funktioniert das Abspielen auch bei wackeligem Netz sofort.
5. Die Aufträge aus `queue` arbeitet der Pi **der Reihe nach** ab (älteste zuerst):
   - Zu Beginn meldet er `playing`, danach `done` bzw. `failed`.
   - Ein Auftrag `stop` beendet sofort die laufende Wiedergabe.
6. **Geplante Einspieler** (Migration 136): Erreicht ein geplanter Einspieler seine Startzeit, legt der Server bei der nächsten Abfrage von `state` automatisch einen normalen Auftrag (`jingle`/`audio`) in `queue` an. Der Pi muss dafür nichts Besonderes tun. Zur Information, etwa für eine Anzeige, liefert `state` die kommenden Einspieler zusätzlich in `scheduled`.
7. Aufträge, die länger als „Aufträge verfallen nach (Min.)“ (Standard 10) nicht abgeholt wurden, bricht der Server selbst ab. So wird nichts Veraltetes nachgespielt, wenn das Gerät wieder online kommt.

Die **aktive Veranstaltung** eines Geräts ist die zugeordnete Veranstaltung, die heute läuft. Als Fenster gilt der Zeitraum vom Vortag des Beginns bis zum Tag nach dem Ende. Bei Überschneidung zählt die früher beginnende Veranstaltung.

## Authentifizierung

Jede Anfrage sendet den Header `Authorization: Bearer <Geräte-Token>`. Bei ungültigem oder deaktiviertem Token antwortet der Server mit `401` und `{"error": "…"}`.

Es gibt weder CSRF-Schutz noch Cookies. Alle Pfade sind relativ zur Basis-URL der Verwaltung, z. B. `https://verleih.example.de`.

## Endpunkte

### `GET /api/player/v1/state?version=<pi-software-version>&playing=<was-gerade-läuft>`

Beide Parameter sind optional:
- `version` wird in der Geräteübersicht angezeigt.
- `playing` ist ein Klartext wie „Opener“ oder „Durchsage“. Er erscheint in der Live-Oberfläche als „spielt gerade“. Leer bedeutet: nichts läuft.

```json
{
  "server_time": "2026-11-09T21:13:56+01:00",
  "poll_interval_seconds": 2,
  "player": { "id": "…", "name": "Pi Mischpult" },
  "tts": { "language": "de-DE", "voice": "de_DE-thorsten-high", "rate": 1.0 },
  "gong": { "url": "/api/player/v1/gong", "version": "gong-1a2b3c4d.mp3", "mime": "audio/mpeg" },
  "rental": {
    "id": "…", "name": "Stadtfest", "location": "Marktplatz",
    "start_date": "2026-11-09", "end_date": "2026-11-10", "gong_by_default": true,
    "tts_language": null
  },
  "files": [
    { "id": "…", "kind": "jingle", "title": "Opener", "description": "Vor Programmstart",
      "filename": "opener.mp3", "mime": "audio/mpeg", "size": 123456,
      "version": "<id>.mp3", "url": "/api/player/v1/files/<id>" }
  ],
  "minute_programs": [
    { "id": "…", "name": "Siegerehrung", "start_at": "2026-11-09T18:35:00+01:00",
      "jingle_before": true, "jingle_file_id": "<id>" }
  ],
  "scheduled": [
    { "id": "…", "file_id": "<id>", "play_at": "2026-11-09T19:00:00+01:00", "note": "Einlass" }
  ],
  "queue": [
    { "id": "…", "kind": "tts", "label": "Die Siegerehrung…", "file_id": null, "file_url": null,
      "text": "Die Siegerehrung beginnt in fünf Minuten.", "language": "de-DE", "recording_url": null, "recording_mime": null,
      "with_gong": true, "requested_by": "Veranstalter", "created_at": "…",
      "status_url": "/api/player/v1/requests/<id>/status" }
  ]
}
```

Hinweise zu den Feldern:
- `gong` ist `null`, wenn kein eigener Gong hinterlegt ist. Dann spielt der Pi seinen **eingebauten** Gong.
- `rental` ist `null`, wenn das Gerät gerade keiner laufenden Veranstaltung zugeordnet ist. `files`, `minute_programs` und `queue` sind dann leer.
- `queue[].language` (Migration 138) ist die Sprache der Durchsage als BCP-47-Code, z. B. `en-GB`. Sie ist immer gesetzt: feste Sprache der Veranstaltung, sonst die in der Live-Oberfläche gewählte, sonst `tts.language`. Passt `tts.voice` nicht zur Sprache, soll der Pi eine passende Stimme für `language` wählen. `rental.tts_language` zeigt nur zur Information, ob die Veranstaltung eine feste Sprache hat.
- `tts.voice` ist ein freier, gerätespezifischer Name, z. B. eine Piper-Stimme. Er kann `null` sein. `tts.rate` liegt zwischen 0,5 und 2,0.
- `scheduled` listet nur die noch nicht ausgelösten Einspieler, sortiert nach Zeit. Ist eine Startzeit länger als die Verfallszeit vorbei, ohne dass das Gerät `state` abgefragt hat, markiert der Server den Einspieler als „verpasst“. Er wird dann nicht nachgespielt.
- `minute_programs[].start_at` enthält bereits die live gepflegte Verzögerung. Die Liste ist informativ, z. B. für eine Anzeige oder optionales automatisches Abspielen. Die Live-Oberfläche erzeugt für „Jingle zum Programmpunkt“ normale `jingle`-Aufträge.

### Auftragsarten (`queue[].kind`)

| kind        | Inhalt                                    | Abspielen                                                        |
|-------------|-------------------------------------------|------------------------------------------------------------------|
| `jingle`    | `file_id`/`file_url`                      | Datei abspielen (aus dem lokalen Cache)                          |
| `audio`     | `file_id`/`file_url`                      | Datei abspielen                                                  |
| `tts`       | `text`, `language`                        | ggf. Gong → Text per Sprachausgabe in `language` sprechen (Stimme/Tempo aus `tts.*`) |
| `recording` | `recording_url`, `recording_mime`         | ggf. Gong → Aufnahme abspielen                                   |
| `stop`      | –                                         | laufende Wiedergabe sofort beenden, dann `done` melden           |

Zu den Formaten:
- `with_gong` ist nur bei `tts` und `recording` gesetzt.
- Hochgeladene Dateien sind WAV, FLAC, AAC/M4A oder MP3.
- Mikrofon-Aufnahmen kommen je nach Browser als `audio/webm` (Opus), `audio/mp4` (AAC, iOS) oder `audio/ogg`. Zum Abspielen empfiehlt sich z. B. `ffmpeg`/`ffplay` oder `mpv`.

### `POST /api/player/v1/requests/{id}/status`

Der Body ist JSON (`Content-Type: application/json`) oder ein Formular:

```json
{ "status": "playing" }
{ "status": "done" }
{ "status": "failed", "error": "Datei nicht abspielbar" }
```

- Erlaubte Werte: `playing`, `done`, `failed`, `cancelled`.
- Antwort bei Erfolg: `{"ok": true}`.
- `404`, wenn der Auftrag nicht zur Veranstaltung dieses Geräts gehört. `422` bei ungültigem Status.

### `GET /api/player/v1/files/{id}`

Liefert die Audio-Datei. Range-Anfragen werden unterstützt. Abrufbar sind nur Dateien von Veranstaltungen, die diesem Gerät zugeordnet sind.

### `GET /api/player/v1/requests/{id}/recording`

Liefert die Mikrofon-Aufnahme eines Auftrags.

### `GET /api/player/v1/gong`

Liefert den eigenen Gong. Ist keiner hinterlegt, antwortet der Server mit `404`.

## Empfehlungen für die Pi-Software

- **Lautstärke:** Lautstärke und Ausgabegerät legt der Pi lokal fest. Das Mischpult regelt den Pegel.
- **Netzwerk:**
  - Ausfälle tolerieren und mit Backoff erneut abfragen.
  - Aufträge nicht doppelt abspielen: bereits bearbeitete IDs lokal merken.
- **Wiedergabe:**
  - Zwischen Gong und Durchsage etwa 300 ms Pause einhalten.
  - Laufende Jingles bei einer neuen Durchsage nicht automatisch abbrechen. Abbrechen nur mit `stop`.
- **Sprachausgabe:** Sie läuft offline, z. B. mit Piper. Bevorzugt die Stimme aus `tts.voice` nutzen, sonst eine deutsche Standardstimme.
