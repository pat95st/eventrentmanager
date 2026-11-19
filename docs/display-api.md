# Display Interface for Stage and FOH Displays

Status: migration 168 "Stage- & FOH-Displays". This description is the specification for display clients (stage monitor, confidence monitor, control room screen). The web application itself ships a ready-made browser implementation at `/display`.

The web interface is German; UI labels are quoted in German with an English translation.

## Overview

- Displays are created under **Einstellungen › Veranstaltungen & Planung › Stage- & FOH-Displays** (settings › events & planning › stage & FOH displays). Each display has its own **device token** (`mvd_…`), which is shown only once when the display is created or the token is regenerated.
- Each display has a **role**: `stage` (stage display) or `foh` (FOH display). The role can be changed at any time without anything changing on the device. **A client should therefore support both presentations** and decide what to show based on `display.role` and `display.options`.
- Which **minute program** a display shows is configured centrally:
  1. a fixed minute program, otherwise
  2. the currently running program of a selected event, otherwise
  3. the currently running program of all events running today (day before the start until the day after the end).

  For 2. and 3., the selection can additionally be restricted to a **room**. A running program is chosen (started at the FOH or between start and expected end), otherwise the next upcoming one, otherwise the one started most recently.
- The run sheet data (`runSheet`) has **exactly the same format** as the run sheet API (`/api/runsheet/v1/{token}`, see [stagedisplay-api.md](stagedisplay-api.md)). An existing client for the run sheet API can therefore easily be switched over.

## Authentication

Every request sends `Authorization: Bearer <display token>`. If the token is invalid or the display is deactivated, the server responds with `401` and `{"ok": false, "error": "…"}`.

There are neither cookies nor CSRF protection. All responses send `Access-Control-Allow-Origin: *` (incl. `Authorization` in the allowed headers), so a browser client on another domain can query the API directly. All paths are relative to the base URL of the web application, e.g. `https://verleih.example.de`.

## Endpoints

| Method  | Path                                         | Purpose                        | Allowed for                      |
|---------|----------------------------------------------|--------------------------------|----------------------------------|
| GET     | `/api/display/v1/state?version=<client>`     | Configuration + live state     | every display                    |
| POST    | `/api/display/v1/delay`                      | Set/change delay               | FOH display with „Steuern erlauben“ (allow control) |
| POST    | `/api/display/v1/items/{itemId}/start`       | Start program item             | FOH display with „Steuern erlauben“ |
| POST    | `/api/display/v1/finish`                     | End program                    | FOH display with „Steuern erlauben“ |
| POST    | `/api/display/v1/reset`                      | Reset live state               | FOH display with „Steuern erlauben“ |
| POST    | `/api/display/v1/messages/{id}/seen`         | Mark message as seen           | FOH display with „Steuern erlauben“ |
| POST    | `/api/display/v1/messages/{id}/hide`         | Hide message (also on stage)   | FOH display with „Steuern erlauben“ |

- Every `state` request counts as a heartbeat; after 45 s without a request the display is shown as offline in the web application. `version` (optional) appears in the device overview.
- The POST endpoints accept the same data as the run sheet API (`adopt`, `adjust_seconds`, `delay_seconds`, `delay` or `started_at`), as JSON or as a form. They act on the program the display is currently showing. The response contains `"ok": true` and the complete new state (as `GET …/state`).

Errors: `401` invalid token, `403` display is not allowed to control, `404` unknown program item, `409` no minute program is currently assigned to the display, `422` invalid input.

## Response of `GET /api/display/v1/state`

```json
{
  "apiVersion": 1,
  "serverTime": "2026-11-15T20:12:00+01:00",
  "pollIntervalSeconds": 3,
  "display": {
    "id": "…",
    "name": "Stage monitor left",
    "role": "stage",
    "roleLabel": "Stagedisplay",
    "canControl": false,
    "assignment": { "mode": "rental", "rentalId": "…", "minuteProgramId": null, "room": "Main hall" },
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
  "runSheet": { "…": "format as GET /api/runsheet/v1/{token}, or null" }
}
```

`roleLabel` is a German label provided by the server.

### Fields

| Field | Meaning |
|-------|---------|
| `pollIntervalSeconds` | Recommended interval between requests (setting „Abfrageintervall“ / polling interval). |
| `display.role` | `stage` or `foh`. Determines the presentation. |
| `display.canControl` | `true` only for FOH displays with „Steuern erlauben“ (allow control). Show controls only then. Equals `runSheet.live.canControl`. |
| `display.assignment.mode` | `program` (fixed program), `rental` (running program of an event) or `auto`. For information only. |
| `display.options.notes` | Trades whose notes should be shown: `general`, `sound`, `light`, `video`, `stage` (keys of `runSheet.*.notes`). Notes of freely created trades are in `runSheet.*.tradeNotes` (see stagedisplay-api.md). |
| `display.options.show*` | Clock, delay, next item, item list, description, number of microphones on/off. |
| `display.options.theme` | `dark` or `light`. |
| `display.options.fontScale` | Font size in percent (50–200). |
| `display.thresholds` | Remaining time in seconds from which the countdown should be shown yellow (`warnSeconds`) or red (`criticalSeconds`, and when overrunning). |
| `display.configRevision` | Changes as soon as the display's configuration changes. Use together with `runSheet.revision` to "redraw only on change". |
| `runSheet` | Live state of the minute program shown, or `null` if none is currently assigned (then show e.g. the display name and "no program"). |
| `runSheet.messages` | Active messages from the organizer (format see [stagedisplay-api.md](stagedisplay-api.md)). **Stage displays** receive only messages to the stage (`stage = true`), **FOH displays** receive all. An FOH display with control can mark them as seen or hide them. |

Within `apiVersion` 1 only new fields are added; clients must ignore unknown fields (including unknown options).

## Recommendations

- Poll at intervals of `pollIntervalSeconds`, and calculate countdowns locally every second from `startsAt`/`endsAt` and the clock offset (`serverTime`) – see the recommendations in [stagedisplay-api.md](stagedisplay-api.md).
- On network errors, keep showing the last state and visibly mark it as "offline".
- Show `event.noStrobe` or `current.noStrobe` clearly as "No strobe".
- Show messages (`runSheet.messages`) prominently, urgent ones (`urgent`) especially conspicuously.

## Browser client `/display`

The web application ships a ready-made client at `/display` (a single page, no login). On the first visit the display token is entered and stored in the browser. Alternatively, open the link `/display#token=mvd_…` that the settings page shows after creating the display – the fragment is not sent to the server and is then removed from the address bar. The `f` key toggles full-screen mode. For kiosk devices (e.g. a Raspberry Pi with Chromium in kiosk mode) it is enough to set this address as the start page.
