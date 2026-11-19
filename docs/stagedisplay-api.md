# Run Sheet Interface for Stage Displays (Run Sheet API)

Status: migration 158 "Ablauf am FOH" (run sheet at the FOH). This description is the specification for a separately developed stage display (stage monitor, confidence monitor, countdown display or similar). The FOH page **Ablauf am FOH** (`/minutenprogramme/{id}/foh`) shows the same data.

The web interface is German; UI labels are quoted in German with an English translation.

> For permanently installed displays with their own device token, central assignment and a role (stage display/FOH display), the display interface is available since migration 168, see [display-api.md](display-api.md). It returns the run sheet data in the same format.

## Overview

- A **minute program** consists of program items with a duration. The planned start time of an item is the program start plus the sum of the durations before it.
- The **delay** is maintained at the FOH – either as an exact value (e.g. `+4:30`) or by pressing **Start** on a program item. Then: delay = actual start − planned start.
- After a start, this item stays **current** until the next one is started ("FOH mode", `live.tracking = true`). If it overruns, the **projected delay** (`live.projectedDelaySeconds`) grows every second without anyone having to enter anything.
- Without a start, "clock mode" applies: the current item is the last one whose shifted start time has been reached.
- **End program** marks the program as finished (`live.state = "finished"`), **Reset** restores the original plan.
- **Messages** (migration 167): the organizer can send short texts to the stage display and/or the FOH via the live page „Audio & Durchsagen“ (audio & announcements, `/live/{id}`). The stage display receives all active messages addressed to the stage in `messages`. A message stays active until the sender ends it or the FOH hides it – then it is missing from the next response. The **FOH** can also send messages to the stage on the page „Ablauf am FOH“ (`fromFoh = true`); they stay active until they are withdrawn.

## Access (token)

Access is granted by the token of a **share link** of the minute program (*Minutenprogramm › Freigabe-Links* / minute program › share links):

| Link mode                                  | Read | Control (delay, start, end, reset) |
|--------------------------------------------|:----:|:----------------------------------:|
| „Nur lesen“ (read only)                    |  ✓   |                 –                  |
| „Lesen & bearbeiten“ (read & edit)         |  ✓   |                 ✓                  |

For a pure display device, use a **read only** link. If the link is revoked, access is blocked immediately (`404`).

The token is part of the path. There are neither cookies nor CSRF protection. All responses send `Access-Control-Allow-Origin: *`, so a browser application on another domain can query the API directly. All paths are relative to the base URL of the web application, e.g. `https://verleih.example.de`.

## Endpoints

| Method  | Path                                              | Purpose                       | Mode               |
|---------|---------------------------------------------------|-------------------------------|--------------------|
| GET     | `/api/runsheet/v1/{token}`                        | Complete live state           | read               |
| POST    | `/api/runsheet/v1/{token}/delay`                  | Set/change delay              | read & edit        |
| POST    | `/api/runsheet/v1/{token}/items/{itemId}/start`   | Start program item            | read & edit        |
| POST    | `/api/runsheet/v1/{token}/finish`                 | End program                   | read & edit        |
| POST    | `/api/runsheet/v1/{token}/reset`                  | Reset live state              | read & edit        |

POST data may be sent as JSON (`Content-Type: application/json`) or as a form (`application/x-www-form-urlencoded`). Every successful POST response contains `"ok": true` and additionally the complete new live state (same format as `GET`).

### `POST …/delay`

Exactly one of the following values (precedence in this order):

| Field            | Example      | Meaning                                                           |
|------------------|--------------|-------------------------------------------------------------------|
| `adopt`          | `true`       | Apply the projected delay (incl. the current overrun)             |
| `adjust_seconds` | `60`, `-300` | Change relative to the current delay                              |
| `delay_seconds`  | `270`        | Absolute delay in seconds (negative = ahead of schedule)          |
| `delay`          | `"4:30"`     | Absolute delay as text: minutes (`5`, `-2`, `1,5`), `mm:ss` or `h:mm:ss` |

At most ±24 hours.

### `POST …/items/{itemId}/start`

Without data, "now" is used. Optionally `started_at` if the item started earlier:
`"20:13:20"` (time of day, assigned to the day closest to the planned time) or an ISO 8601 timestamp.

### Errors

`{"ok": false, "error": "…"}` with

- `403` – the link only allows reading,
- `404` – unknown/revoked token or unknown program item,
- `422` – invalid input (e.g. delay not parseable).

## Response of `GET /api/runsheet/v1/{token}`

```json
{
  "apiVersion": 1,
  "serverTime": "2026-11-15T20:12:00+01:00",
  "revision": "bd7c8561a5b09953",
  "event": { "name": "Company gala 2026", "noStrobe": false },
  "program": {
    "id": "…", "name": "Evening program", "room": "Main hall",
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
    "id": "…", "position": 2, "name": "Welcome", "description": "…",
    "notes": { "general": "…", "sound": "HH 1", "light": "…", "video": null, "stage": null },
    "tradeNotes": [
      { "tradeId": "…", "trade": "Ton", "color": "#3b82f6", "text": "HH 1" },
      { "tradeId": "…", "trade": "Licht", "color": "#f59e0b", "text": "…" },
      { "tradeId": "…", "trade": "Rigging", "color": "#a855f7", "text": "Banner flies in" }
    ],
    "noStrobe": true,
    "durationSeconds": 300,
    "startsAt": "2026-11-15T20:11:00+01:00",
    "endsAt": "2026-11-15T20:16:00+01:00",
    "elapsedSeconds": 60,
    "remainingSeconds": 240,
    "actualStart": "2026-11-15T20:11:00+01:00"
  },
  "next": { "…": "same format as current" },
  "items": [
    {
      "id": "…", "position": 1, "name": "Doors open", "description": null,
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
      "text": "Speaker arrives 5 minutes late",
      "urgent": false,
      "stage": true, "foh": true,
      "allPrograms": true,
      "sentAt": "2026-11-15T20:09:30+01:00",
      "sentBy": "Max Muster (Organizer)",
      "fromFoh": false,
      "acknowledgedAt": null, "acknowledgedBy": null
    }
  ]
}
```

The trades „Ton“ (sound) and „Licht“ (light) are user-defined names; see `notes` below for why these German names matter.

### Fields

| Field | Meaning |
|-------|---------|
| `serverTime` | Server time of the response. To compensate for the device clock: `offset = serverTime − local time on receipt`. |
| `revision` | Changes as soon as the plan or live state changes (not with the time of day). Use it to "redraw only on change". |
| `live.state` | `pending` (before the start), `running`, `finished`. |
| `live.tracking` | `true`: the current item was set by a start and stays current until the next one starts. `false`: clock mode. |
| `live.delaySeconds` | Delay set (negative = ahead of schedule). |
| `live.projectedDelaySeconds` | Delay including the current overrun of the current item. **This is the value to display.** |
| `live.overrunSeconds` | How far the current item runs past the planned start time of the next one. |
| `live.canControl` | The token is allowed to control. |
| `current` / `next` | Current or next item, or `null`. `startsAt` = actual or expected start, `endsAt` = start + duration (`null` without a duration). `elapsedSeconds` is negative for the next item (= time until start). `remainingSeconds < 0` = overrun. |
| `items[].status` | `done`, `current`, `next`, `upcoming`. |
| `items[].plannedStart` | Original plan without delay. |
| `items[].currentStart` | Plan + delay set. |
| `items[].projectedStart` | Actual start (if started), otherwise expected start incl. the current overrun. |
| `trades` | Trades for which the minute program keeps notes (configured or automatically from the event/technicians), in the order of the settings. `color` is a fixed color per trade (`#rrggbb`). |
| `tradeNotes` | Notes per trade (for `current`, `next`, `items[]`): only non-empty notes of the trades from `trades`, in their order. **Use this for new displays.** |
| `notes` | `general`: general note. `sound`, `light`, `video`, `stage`: compatibility fields from the time of fixed trades – filled with the note of the trade named „Ton“, „Licht“, „Video“ or „Bühne“ respectively (otherwise `null`). Notes of other trades are only in `tradeNotes`. |
| `event.noStrobe` | `true`: no strobe light may be used during the entire event. |
| `noStrobe` (for `current`, `next`, `items[]`) | `true`: no strobe light may be used during this program item. Applies in addition to `event.noStrobe` – a display should show the notice if either of the two is `true`. |
| `messages` | Active messages from the organizer and the FOH to the stage, oldest first (empty array if there are none). Only messages with `stage = true` are returned by the API; FOH-only messages appear only on the FOH page. |
| `messages[].text` | Message text (max. 500 characters, may contain line breaks). |
| `messages[].urgent` | Marked as urgent – show conspicuously (e.g. red, blinking). |
| `messages[].stage` / `foh` | Targets the message was sent to. |
| `messages[].sentBy` / `fromFoh` | Display name of the sender; `fromFoh = true`: written at the FOH (otherwise by the organizer or from the live page). |
| `messages[].allPrograms` | `true`: applies to all minute programs of the event, `false`: only to this one. |
| `messages[].acknowledgedAt` / `acknowledgedBy` | When/by whom the FOH marked the message as seen (otherwise `null`). It nevertheless stays active. |

All times are ISO 8601 with a time zone offset. Within `apiVersion` 1 only new fields are added; clients must ignore unknown fields.

## Recommendations for the stage display

- Poll `GET` every **2–5 s**. Calculate countdowns locally every second from `startsAt`/`endsAt` and the clock offset – do not wait for the next response.
- In FOH mode (`live.tracking`), extrapolate the delay locally: if the next item is overdue, `projectedDelaySeconds ≈ delaySeconds + (now − next.currentStart)` (value `currentStart` from `items`).
- On network errors, keep showing the last state and visibly mark it as "offline".
- Show messages (`messages`) legibly above or next to the countdown; briefly highlight new messages (unknown `id`). If an `id` is missing from a later response, the message has been ended and must be hidden.
- Example (browser):

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
