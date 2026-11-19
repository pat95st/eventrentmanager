# Partner Instances (Federation Between Installations)

Status: migration 160 "Partner-Instanzen" (partner instances). Two installations of this software can become "friends". Afterwards they can see each other's material pool, request rentals and request technicians.

The web interface is German; UI labels are quoted in German with an English translation.

## Flow from the users' point of view

1. **Instance A** (inviting): *Einstellungen › Partner-Instanzen › Link erstellen* (settings › partner instances › create link). The link is valid for 7 days and can be used exactly once. Only the SHA-256 hash of the code is stored; the link is shown only right after it has been created.
2. **Instance B** enters the link under *Einladungslink eines Partners eingeben* (enter a partner's invitation link). B sees the name and address of A and decides:
   - whether material requests from A are entered and confirmed automatically,
   - whether A may see and request B's technicians,
   - the discount: the default is **the same discount in both directions**. Without the checkbox, two different values can be proposed.
3. **Instance A** sees the partnership as „Bestätigung erforderlich“ (confirmation required), sets its own settings and confirms. If A accepts the proposed discount unchanged, it applies immediately. Changed values are sent to B as a counter-proposal.
4. Every later discount change is a proposal the other side must agree to (*Zustimmen* / agree, *Ablehnen* / decline, *Vorschlag zurückziehen* / withdraw proposal). Until then, the previously agreed discount applies.

The settings "confirm automatically" and "share technicians" are **local**: each side decides for itself how to handle the partner's requests.

### Requesting material

*Veranstaltungen › Partner › Materialpool* (events › partners › material pool), or on the event page in the box „Partner“ › „Material“: choose a period. The partner's rentable articles are shown with their availability in the period and the daily price minus the agreed discount. Enter quantities and click *Anfrage senden* (send request).

At the partner:
- **Confirm automatically** enabled and everything available: an event is created immediately for the customer representing the partner. The material is scheduled and the request is confirmed.
- Otherwise the request stays open (*Veranstaltungen › Partner*, „Offene Anfragen von Partnern“ / open requests from partners) and is confirmed or declined manually. The requester is notified immediately.

On the first confirmation, the customer representing the partner is created from the partner's name and address. Missing address parts are filled with "–". Under *Einstellungen › Partner-Instanzen › (partner)* an existing customer can be chosen instead.

If an invoice is created from an event created this way, the agreed partner discount is prefilled as a percentage discount.

### Requesting technicians

*Veranstaltungen › Partner › Techniker anfragen* (events › partners › request technicians): specify the event, period, number, trades and optionally a preferred technician. When confirming, the partner selects its technicians. An event is created for them, and they are requested or scheduled and notified as usual (migration 134). The requester sees the scheduled technicians with their status (*Stand abfragen* / fetch status retrieves the current state).

Only the technicians' first and last names and the names of the trades are shared with the partner, no e-mail addresses.

## Protocol

All paths are relative to the base URL of the respective instance. The base URL comes from *Einstellungen › Integrationen* (settings › integrations). If it is not set, it is derived from the current address. Data is exchanged as JSON. Errors are reported as `{"error": "…"}` with a suitable HTTP status. There is neither a session nor CSRF. Redirects are not followed.

Values are always given **from the sender's point of view**. `discount_granted` is therefore the discount the sender grants the recipient.

### Invitation (authenticated by the secret code)

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/federation/v1/invitations/{code}` | Name, base URL and address of the inviting instance |
| `POST` | `/api/federation/v1/invitations/{code}/accept` | Accept the invitation. Body: `name`, `base_url`, `profile`, `token` (the inviting instance will use this token to call the sender in the future), `discount_granted`, `discount_received`. Response: own `token` for the sender |

Afterwards every request sends `Authorization: Bearer <token>`. Each side stores only the SHA-256 hash of the token it issued.

### Partnership

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/federation/v1/partnership` | Status, discounts, name/address (also a connection test) |
| `POST` | `/api/federation/v1/partnership/confirm` | `{"decision": "accept"|"decline", "discount": {"action": "accept"|"propose", "granted": …, "received": …}}` |
| `POST` | `/api/federation/v1/partnership/discount` | `{"action": "propose"|"accept"|"reject", "granted": …, "received": …}` |
| `POST` | `/api/federation/v1/partnership/end` | End the partnership. Afterwards all endpoints respond with `403` |

### Material and technicians

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/federation/v1/articles?from=Y-m-d&to=Y-m-d&q=&page=` | Rentable articles with `total`, `available` (in the period), `daily_price`, `discounted_daily_price` |
| `GET` | `/api/federation/v1/technicians` | `allowed`, `technicians` (`id`, `name`), `trades` |
| `POST` | `/api/federation/v1/requests` | New request. Body: `reference` (UUID, identical on both sides), `kind` (`material`/`technician`), `event_name`, `start_date`, `end_date`, `event_location`, `note`, `contact_name`, `contact_email`; for material additionally `items` (`article_id`, `quantity`), for technicians `trades`, `technician_count`, `preferred_technician_id`. Sending the same `reference` again returns the existing state |
| `GET` | `/api/federation/v1/requests/{reference}` | Current state of a request made by the sender |
| `POST` | `/api/federation/v1/requests/{reference}/cancel` | The requester cancels |
| `POST` | `/api/federation/v1/requests/{reference}/status` | The provider reports a decision (`status`, `message`, `items`, `technicians`, `discount_percent`) |

Response of the request endpoints: `reference`, `kind`, `status` (`pending`, `accepted`, `declined`, `cancelled`), `message`, `discount_percent`, `items`, `technicians`.

If the partner cannot be reached when a decision is sent, this is noted on the request. The response can then be sent again (*Antwort erneut an Partner senden* / resend response to partner), or the requester fetches the state itself.
