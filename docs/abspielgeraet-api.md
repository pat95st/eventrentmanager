# Player Device Interface (Raspberry Pi at the Mixing Desk)

Status: migration 135 "Veranstaltungs-Audio" (event audio), pairing by code since migration 141. This description is the specification for the Pi software, which is developed separately.

The web interface is German; UI labels are quoted in German with an English translation.

## Flow

1. Create the device and assign it to the event – two ways:
   - **By code (recommended, migration 141):** In the event, generate a 6-digit code under **Audio & Durchsagen › Abspielgerät per Code koppeln** (audio & announcements › pair player device by code). Enter the address of the web application and the code on the Pi. The Pi calls `POST /api/player/v1/pair`, receives its device token and is immediately assigned to the event.
   - **Manually:** Create a player device under **Einstellungen › Veranstaltungs-Audio** (settings › event audio). The device token (`mvp_…`) is shown **only once** and must be stored on the Pi.
2. If created manually: assign the device in the event under **Audio & Durchsagen**.
3. The Pi polls `GET /api/player/v1/state` every `poll_interval_seconds` (currently 2 s):
   - Every poll counts as a heartbeat. After 45 s without a poll the device is considered offline.
   - The response contains the active event, all audio files (manifest), the gong, the text-to-speech settings and the queue.
4. The Pi keeps the files locally:
   - It downloads new or changed files, recognizable by the field `version`.
   - It deletes files that are no longer in the manifest.
   - This way playback starts immediately even on a flaky network.
5. The Pi processes the jobs in `queue` **in order** (oldest first):
   - At the start it reports `playing`, afterwards `done` or `failed`.
   - A `stop` job immediately ends the current playback.
6. **Scheduled playback cues** (migration 136): when a scheduled cue reaches its start time, the server automatically adds a normal job (`jingle`/`audio`) to `queue` on the next `state` poll. The Pi does not need to do anything special for this. For information, e.g. for a display, `state` additionally lists the upcoming cues in `scheduled`.
7. Jobs that have not been picked up for longer than „Aufträge verfallen nach (Min.)“ (jobs expire after (min.), default 10) are cancelled by the server itself. This prevents outdated content from being played when the device comes back online.

The **active event** of a device is the assigned event that is running today. The window is the period from the day before the start until the day after the end. If events overlap, the one that starts earlier wins.

## Authentication

Every request sends the header `Authorization: Bearer <device token>`. If the token is invalid or deactivated, the server responds with `401` and `{"error": "…"}`.

There is neither CSRF protection nor cookies. All paths are relative to the base URL of the web application, e.g. `https://verleih.example.de`.

## Endpoints

### `POST /api/player/v1/pair` (migration 141)

Pairs a device with an event using a 6-digit code. The endpoint requires **no** bearer token.

```json
{ "code": "123456", "name": "Pi mixing desk", "version": "1.0.0" }
```

- `code`: required. Spaces and hyphens are ignored (`123 456`).
- `name`: optional, device name in the web application (default "AutoEventPlay").
- If the device additionally sends `Authorization: Bearer <existing token>` and that token is valid, the **existing** device is assigned to the new event. It keeps its token, and the response then contains `"token": null`.
- Otherwise the server creates a new device and returns the token **once** in `token`.

Response on success:

```json
{
  "ok": true,
  "token": "mvp_…",
  "player": { "id": "…", "name": "Pi mixing desk" },
  "rental": { "id": "…", "name": "City festival", "location": "Market square", "start_date": "2026-11-09", "end_date": "2026-11-10" },
  "poll_interval_seconds": 2
}
```

Errors:
- `422` with `{"error": "Der Code ist ungültig oder abgelaufen."}` (error messages are German).
- `429` if 10 failed attempts came from the same IP address within 15 minutes.

A code is valid for 15 minutes and for one use only. Generating a new code invalidates the previous code of the same event.

### `GET /api/player/v1/state?version=<pi-software-version>&playing=<what-is-playing>`

Both parameters are optional:
- `version` is shown in the device overview.
- `playing` is plain text such as "Opener" or "Announcement". It appears in the live interface as "currently playing". Empty means: nothing is playing.

```json
{
  "server_time": "2026-11-09T21:13:56+01:00",
  "poll_interval_seconds": 2,
  "player": { "id": "…", "name": "Pi mixing desk" },
  "tts": { "language": "de-DE", "voice": "de_DE-thorsten-high", "rate": 1.0 },
  "gong": { "url": "/api/player/v1/gong", "version": "gong-1a2b3c4d.mp3", "mime": "audio/mpeg" },
  "rental": {
    "id": "…", "name": "City festival", "location": "Market square",
    "start_date": "2026-11-09", "end_date": "2026-11-10", "gong_by_default": true,
    "tts_languages": ["de-DE", "en-GB"]
  },
  "files": [
    { "id": "…", "kind": "jingle", "title": "Opener", "description": "Before the show starts",
      "filename": "opener.mp3", "mime": "audio/mpeg", "size": 123456,
      "version": "<id>.mp3", "url": "/api/player/v1/files/<id>" }
  ],
  "minute_programs": [
    { "id": "…", "name": "Award ceremony", "start_at": "2026-11-09T18:35:00+01:00",
      "jingle_before": true, "jingle_file_id": "<id>" }
  ],
  "scheduled": [
    { "id": "…", "file_id": "<id>", "play_at": "2026-11-09T19:00:00+01:00", "note": "Doors open",
      "repeat_interval_minutes": 10, "repeat_until": "2026-11-09T22:00:00+01:00", "repeat_max": null, "repeat_count": 3 }
  ],
  "queue": [
    { "id": "…", "kind": "tts", "label": "The award ceremony…", "file_id": null, "file_url": null,
      "text": "The award ceremony starts in five minutes.", "language": "en-GB", "recording_url": null, "recording_mime": null,
      "with_gong": true, "requested_by": "Organizer", "created_at": "…",
      "status_url": "/api/player/v1/requests/<id>/status" }
  ]
}
```

Notes on the fields:
- `gong` is `null` if no custom gong has been uploaded. The Pi then plays its **built-in** gong.
- `rental` is `null` if the device is currently not assigned to a running event. `files`, `minute_programs` and `queue` are empty in that case.
- `queue[].language` (migration 138) is the language of the announcement as a BCP 47 code, e.g. `en-GB`. It is always set: the fixed language of the event, otherwise the one selected in the live interface, otherwise `tts.language`. If `tts.voice` does not match the language, the Pi should pick a suitable voice for `language`. `rental.tts_languages` (migration 140) lists the fixed languages of the event for information only. With several languages, multilingual announcements arrive as individual `tts` jobs directly one after another, one per language. Only the first has `with_gong`; the Pi simply plays them in order.
- `tts.voice` is a free, device-specific name, e.g. a Piper voice. It may be `null`. `tts.rate` is between 0.5 and 2.0.
- Recurring playback cues (migration 139) appear in `scheduled` as one entry with `repeat_*`. `play_at` is always the next date. Each repetition is triggered as a normal job in `queue`, as usual.
- `scheduled` lists only cues that have not been triggered yet, sorted by time. If a start time passed longer ago than the expiry time without the device polling `state`, the server marks the cue as "missed". It is then not played afterwards.
- `minute_programs[].start_at` already includes the delay maintained live. The list is informational, e.g. for a display or optional automatic playback. For „Jingle zum Programmpunkt“ (jingle for program item), the live interface creates normal `jingle` jobs.

### Job types (`queue[].kind`)

| kind        | Content                                   | Playback                                                         |
|-------------|-------------------------------------------|------------------------------------------------------------------|
| `jingle`    | `file_id`/`file_url`                      | Play the file (from the local cache)                             |
| `audio`     | `file_id`/`file_url`                      | Play the file                                                    |
| `tts`       | `text`, `language`                        | Gong if applicable → speak the text via text-to-speech in `language` (voice/rate from `tts.*`) |
| `recording` | `recording_url`, `recording_mime`         | Gong if applicable → play the recording                          |
| `stop`      | –                                         | Stop the current playback immediately, then report `done`        |

About the formats:
- `with_gong` is only set for `tts` and `recording`.
- Uploaded files are WAV, FLAC, AAC/M4A or MP3.
- Depending on the browser, microphone recordings arrive as `audio/webm` (Opus), `audio/mp4` (AAC, iOS) or `audio/ogg`. For playback, e.g. `ffmpeg`/`ffplay` or `mpv` are recommended.

### `POST /api/player/v1/requests/{id}/status`

The body is JSON (`Content-Type: application/json`) or a form:

```json
{ "status": "playing" }
{ "status": "done" }
{ "status": "failed", "error": "File not playable" }
```

- Allowed values: `playing`, `done`, `failed`, `cancelled`.
- Response on success: `{"ok": true}`.
- `404` if the job does not belong to this device's event. `422` for an invalid status.

### `GET /api/player/v1/files/{id}`

Returns the audio file. Range requests are supported. Only files of events assigned to this device can be retrieved.

### `GET /api/player/v1/requests/{id}/recording`

Returns the microphone recording of a job.

### `GET /api/player/v1/gong`

Returns the custom gong. If none has been uploaded, the server responds with `404`.

## Recommendations for the Pi software

- **Volume:** The Pi sets volume and output device locally. The mixing desk controls the level.
- **Network:**
  - Tolerate outages and poll again with backoff.
  - Do not play jobs twice: remember the IDs already processed locally.
- **Playback:**
  - Keep a pause of about 300 ms between the gong and the announcement.
  - Do not automatically stop running jingles when a new announcement arrives. Stop only on `stop`.
- **Text-to-speech:** It runs offline, e.g. with Piper. Prefer the voice from `tts.voice`, otherwise a default German voice.
