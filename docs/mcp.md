# MCP Server for AI Assistants

Status: migrations 183 "MCP-Server" and 184 "MCP-OAuth". The application contains a server for the
[Model Context Protocol](https://modelcontextprotocol.io) (MCP) at `/mcp`. AI assistants such as Claude
(claude.ai, Claude Desktop, Claude Code) or your own agents use it to look up and edit customers,
articles, availability, events, offers and invoices — e.g. "Which events are next weekend?", "Are 8 tops
and 2 subs available from 5 to 6 December?" or "Create an offer for customer Müller with …".

The web interface is German; UI labels are quoted in German with an English translation.

## Overview

- **Endpoint:** `https://<your installation>/mcp`, transport "Streamable HTTP" (protocol versions
  `2025-06-18`, `2025-03-26`, `2024-11-05`). Every request is one JSON-RPC message sent with `POST` and is
  answered with one JSON response; there is no SSE stream and no session, so it runs on any PHP hosting.
- **Built on the REST API v1:** the tools call the same controllers, commands and validations as
  `/api/v1`. Tokens, scopes, IP filter, request limit and the log are shared with the REST API.
- **Two ways to sign in:** an API token released for MCP (Claude Code, own agents), or **OAuth 2.1** with
  the user's normal login (claude.ai, Claude Desktop and other clients that cannot store a fixed token).
- **Only tools, no resources or prompts.** About 30 task-oriented tools (list below). Results are compact
  JSON (empty fields dropped) and contain a `url` to the record in the web interface instead of PDFs.
- **Never possible via MCP:** finalizing or cancelling invoices, sending e-mails to customers, deleting
  records, changing articles or master data, writing receipts or purchase orders, issuing/returning
  material. These stay in the web interface.

## Setup

1. **Einstellungen › API** (Settings › API): tick „API ist aktiviert“ (API is enabled) and
   „MCP-Server ist aktiviert“ (MCP server is enabled). MCP requires the REST API; while either switch is
   off, `/mcp` answers `404`.
2. Optional: tick **„MCP nur lesen“** (MCP read only). Then no write tool is offered to any token, whatever
   its scopes.
3. Create a token (or edit one) with the scopes the assistant should have and tick
   **„Für MCP zulassen“** (allow for MCP). Existing tokens are not released for MCP automatically.
   The token's own „Nur lesen“ (read only) switch also hides all write tools.

Effective permissions are always the strictest combination of: global „MCP nur lesen“, the token's
„Nur lesen“, and the token's scopes.

### claude.ai and Claude Desktop (OAuth)

These clients connect with the user's own login instead of a fixed token:

1. Steps 1–2 above (no token is needed).
2. In the client add a custom connector with the URL `https://<your installation>/mcp`
   (claude.ai: Settings › Connectors › Add custom connector). Each user can copy the URL from
   **Profil › KI-Assistenten** (Profile › AI assistants).
3. The client registers itself, opens the login page of the installation (password, passkey, LDAP or
   OAuth/OIDC login with two-factor authentication as usual) and then a **consent page**: it shows the
   client's name and the address it returns to, and lists the scopes the user may grant; the user ticks
   what the assistant may do and clicks „Zugriff erlauben“ (allow access).

Rules:

- Only scopes derived from the user's own permissions are offered (see table below); `invoices:finalize`
  and `email:send` are never granted. Users who only have the permission „Techniker“ cannot connect
  (they only see the events they are assigned to, which scopes cannot express).
- Access tokens are valid for 1 hour and are renewed automatically with a refresh token, which is replaced
  on every use and expires after 90 days without use. Reusing an old refresh token ends the connection.
- On every request the user's current permissions are checked again: a deactivated user has no access at
  once, withdrawn permissions remove the corresponding tools.
- OAuth tokens are valid for `/mcp` only, not for the REST API. They do not appear in the token list;
  every user sees and disconnects their connections under Profil › KI-Assistenten, administrators see all
  connections under Settings › API („Verbundene KI-Assistenten“).

| Permission of the user | Scopes that can be granted |
|---|---|
| Administration | all scopes except `invoices:finalize` and `email:send` |
| Kundenverwaltung | `customers:read`, `customers:write` |
| Artikelverwaltung | `articles:*`, `master_data:*`, `inventory:*`, `finance:*` |
| Ausgabe und Rücknahme | `rentals:*`, `sales:*`, `invoices:read`, `invoices:write`, `customers:read`, `articles:read`, `master_data:read` |
| Minutenprogramme | `minute_programs:read` |
| Elektroprüfung | `inventory:read`, `inventory:write`, `articles:read` |

#### OAuth endpoints (for client developers)

| Endpoint | Purpose |
|---|---|
| `GET /.well-known/oauth-protected-resource` | Protected resource metadata (RFC 9728); `/mcp` answers `401` with `WWW-Authenticate: Bearer resource_metadata="…"` |
| `GET /.well-known/oauth-authorization-server` | Authorization server metadata (RFC 8414) |
| `POST /oauth/register` | Dynamic client registration (RFC 7591), open; redirect URIs must use https, or http with `localhost`/`127.0.0.1`/`[::1]` (any port) |
| `GET /oauth/authorize` | Authorization code flow, PKCE with `S256` required, optional `scope` (space separated) and `resource` (must be the `/mcp` URL) |
| `POST /oauth/token` | `authorization_code` and `refresh_token` grants; public clients (`none`) or `client_secret_post`/`client_secret_basic` |
| `POST /oauth/revoke` | Token revocation (RFC 7009); revoking a refresh token ends the connection |

Registration and token endpoints are limited per IP address (10 and 60 requests per minute). All
endpoints answer `404` while the REST API or the MCP server is switched off.

### Claude Code

```bash
claude mcp add --transport http materialverleih https://verleih.example.de/mcp --header "Authorization: Bearer mv_…"
```

### Other clients with a fixed token

Any MCP client that supports Streamable HTTP with a custom header works the same way: URL
`https://<your installation>/mcp`, header `Authorization: Bearer mv_…`.

### Quick test with curl

```bash
curl -s https://verleih.example.de/mcp -H "Authorization: Bearer mv_…" -H "Content-Type: application/json" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

## Permissions

`tools/list` only returns the tools the token may use. A tool needs all scopes listed below; write tools
are additionally hidden while the token or the global MCP setting is read only. `convert_offer` checks the
scopes of the chosen target when it is called (order confirmation: `sales:write`; event: `sales:write` and
`rentals:write`; invoice draft: `invoices:write`). `get_inventory_number` includes electrical inspections
only with `inventory:read`; `create_offer`/`update_offer` fill in missing rental prices from the article
only with `articles:read`.

## Tools

| Tool | Type | Scopes | Purpose |
|---|---|---|---|
| `search_customers` | read | `customers:read` | Search customers (Kunden) |
| `get_customer` | read | `customers:read` | Customer with contact persons |
| `create_customer` | write | `customers:write` | Create a customer |
| `update_customer` | write | `customers:write` | Change a customer (only the fields sent) |
| `search_articles` | read | `articles:read` | Search the equipment catalogue (Artikel) |
| `get_article` | read | `articles:read` | Article with its inventory numbers |
| `check_availability` | read | `articles:read` | Available units of up to 100 articles in a period |
| `list_rentals` | read | `rentals:read` | Events (Veranstaltungen), by search text or customer |
| `get_calendar` | read | `rentals:read` | Events overlapping a period, with material |
| `get_rental` | read | `rentals:read` | Event with items, issued units, logistics and technicians |
| `create_rental` | write | `rentals:write` | Create an event |
| `update_rental` | write | `rentals:write` | Change an event, incl. pickup/return (`logistics`) |
| `add_rental_items` | write | `rentals:write` | Schedule articles for an event |
| `update_rental_item` | write | `rentals:write` | Change the quantity of a scheduled item |
| `list_minute_programs` | read | `minute_programs:read` | Minute programs (Minutenprogramme) of an event |
| `list_offers` | read | `sales:read` | Offers (Angebote) |
| `get_offer` | read | `sales:read` | Offer with items and totals |
| `create_offer` | write | `sales:write` | Create an offer draft (prices default to the article's daily price) |
| `update_offer` | write | `sales:write` | Change an offer (a list that is sent replaces that list) |
| `set_offer_status` | write | `sales:write` | Mark as sent (no e-mail), accepted or declined |
| `convert_offer` | write | `sales:read` + target | Order confirmation, event or invoice draft from an offer |
| `list_order_confirmations` | read | `sales:read` | Order confirmations (Auftragsbestätigungen) |
| `list_invoices` | read | `invoices:read` | Invoices (Rechnungen), incl. filter "overdue" |
| `get_invoice` | read | `invoices:read` | Invoice with items, totals and dunning notices |
| `create_invoice_draft_from_rental` | write | `rentals:read`, `invoices:write` | Invoice draft (no invoice number) from an event |
| `mark_invoice_paid` | write | `invoices:write` | Mark an issued invoice as paid |
| `list_receipts` | read | `finance:read` | Receipts (Belege) |
| `list_purchase_orders` | read | `finance:read` | Purchase orders (Bestellungen) |
| `get_report` | read | `reports:read` | Income/expense, revenue forecast or depreciation |
| `get_inventory_number` | read | `articles:read` | A unit by id or label number, with inspections |
| `list_rmas` | read | `inventory:read` | Repairs/returns (RMA) |
| `record_electrical_inspection` | write | `inventory:write` | Record an electrical inspection (E-Prüfung) |

Write tools take the same fields as the corresponding REST API requests (see `api-docs.html`); the exact
JSON Schema of every tool is returned by `tools/list`.

## Conventions

- Dates `YYYY-MM-DD`, times ISO 8601, amounts in EUR as decimal strings; rental prices are per day.
- List tools take `limit` (default 25, max 100) and `page` and return
  `{"items": [...], "page", "limit", "total", "has_more"}`.
- Tool errors (invalid input, not found, business rule violated, missing permission) are returned as a
  result with `"isError": true` and a German or English message, so the assistant can correct its input.
  Protocol errors (unknown method or tool, malformed JSON) are JSON-RPC errors.
- `initialize` returns short instructions for the model (terminology, conventions, limits).

## Log and data protection

Every `tools/call` is logged under Settings › API („Protokoll schreibender Zugriffe und MCP-Aufrufe“) with
method `MCP`, tool name, arguments (shortened) and number of results — not the result itself — and kept for
90 days like the REST API log. This shows which data was requested by which token. Keep in mind that the
data returned by the tools is processed by the AI provider of the client.

## HTTP status codes

| Status | Meaning |
|---|---|
| `200` | JSON-RPC response (`result` or `error`) |
| `202` | Notification accepted |
| `400` | Body is not JSON, or unsupported `MCP-Protocol-Version` header |
| `401` | Token missing, unknown, disabled or expired (`WWW-Authenticate` points to the OAuth metadata) |
| `403` | Token not released for MCP, or IP address not allowed |
| `404` | REST API or MCP server switched off |
| `405` | `GET`/`DELETE`: no SSE stream, no sessions |
| `429` | Request limit of the token exceeded (`Retry-After`) |
