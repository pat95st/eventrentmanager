# Partner-Instanzen (Föderation zwischen Installationen)

Stand: Migration 157 „Partner-Instanzen“. Zwei Installationen dieser Software können sich „befreunden“. Danach sehen sie gegenseitig ihren Materialpool, fragen Vermietungen an und fragen Techniker an.

## Ablauf aus Sicht der Benutzer

1. **Instanz A** (einladend): *Einstellungen › Partner-Instanzen › Link erstellen*. Der Link ist 7 Tage gültig und genau einmal verwendbar. Gespeichert wird nur der SHA-256-Hash des Codes, angezeigt wird der Link nur direkt nach dem Erstellen.
2. **Instanz B** gibt den Link unter *Einladungslink eines Partners eingeben* ein. B sieht Name und Anschrift von A und legt fest:
   - ob Material-Anfragen von A automatisch eingetragen und zugesagt werden,
   - ob A die Techniker von B sehen und anfragen darf,
   - den Rabatt: Standard ist **der gleiche Rabatt in beide Richtungen**. Ohne Haken lassen sich zwei unterschiedliche Werte vorschlagen.
3. **Instanz A** sieht die Partnerschaft als „Bestätigung erforderlich“, legt ihre eigenen Einstellungen fest und bestätigt. Übernimmt A den vorgeschlagenen Rabatt unverändert, gilt er sofort. Geänderte Werte gehen als Gegenvorschlag an B.
4. Jede spätere Rabattänderung ist ein Vorschlag, dem die andere Seite zustimmen muss (*Zustimmen*, *Ablehnen*, *Vorschlag zurückziehen*). Bis dahin gilt der bisher vereinbarte Rabatt.

Die Einstellungen „automatisch zusagen“ und „Techniker freigeben“ sind **lokal**: Jede Seite entscheidet selbst, wie sie mit Anfragen des Partners umgeht.

### Material anfragen

*Veranstaltungen › Partner › Materialpool* (oder auf der Veranstaltungsseite in der Box „Partner“ › „Material“): Zeitraum wählen. Angezeigt werden die vermietbaren Artikel des Partners mit Verfügbarkeit im Zeitraum und Tagespreis abzüglich des vereinbarten Rabatts. Mengen eintragen und *Anfrage senden*.

Beim Partner gilt:
- **Automatisch zusagen** aktiv und alles verfügbar: Es wird sofort eine Veranstaltung für den Kunden angelegt, der den Partner vertritt. Das Material wird eingeplant und die Anfrage zugesagt.
- Sonst bleibt die Anfrage offen (*Veranstaltungen › Partner*, „Offene Anfragen von Partnern“) und wird manuell zugesagt oder abgelehnt. Der Anfragende wird sofort benachrichtigt.

Der Kunde, der den Partner vertritt, wird bei der ersten Zusage aus Name und Anschrift des Partners angelegt. Fehlende Adressteile werden mit „–“ gefüllt. Unter *Einstellungen › Partner-Instanzen › (Partner)* kann stattdessen ein bestehender Kunde gewählt werden.

Wird aus einer so angelegten Veranstaltung eine Rechnung erstellt, ist der vereinbarte Partnerrabatt als prozentualer Rabatt vorbelegt.

### Techniker anfragen

*Veranstaltungen › Partner › Techniker anfragen*: Veranstaltung, Zeitraum, Anzahl, Gewerke und optional einen Wunsch-Techniker angeben. Der Partner wählt beim Zusagen seine Techniker aus. Für sie wird eine Veranstaltung angelegt, und sie werden wie gewohnt angefragt bzw. verplant und benachrichtigt (Migration 134). Der Anfragende sieht die eingeplanten Techniker samt Status (*Stand abfragen* holt den aktuellen Stand).

Mit dem Partner geteilt werden nur Vor- und Nachname der Techniker und die Namen der Gewerke, keine E-Mail-Adressen.

## Protokoll

Alle Pfade sind relativ zur Basis-URL der jeweiligen Instanz. Die Basis-URL kommt aus *Einstellungen › Integrationen*. Ist sie nicht gesetzt, wird sie aus der aktuellen Adresse abgeleitet. Daten werden als JSON ausgetauscht. Fehler werden als `{"error": "…"}` mit passendem HTTP-Status gemeldet. Es gibt weder Session noch CSRF. Weiterleitungen werden nicht verfolgt.

Werte sind immer **aus Sicht des Absenders** angegeben. `discount_granted` ist also der Rabatt, den der Absender dem Empfänger gewährt.

### Einladung (Authentifizierung über den geheimen Code)

| Methode | Pfad | Zweck |
|---|---|---|
| `GET` | `/api/federation/v1/invitations/{code}` | Name, Basis-URL und Anschrift der einladenden Instanz |
| `POST` | `/api/federation/v1/invitations/{code}/accept` | Einladung annehmen. Body: `name`, `base_url`, `profile`, `token` (mit diesem Token ruft die einladende Instanz künftig den Absender auf), `discount_granted`, `discount_received`. Antwort: eigenes `token` für den Absender |

Danach sendet jede Anfrage `Authorization: Bearer <Token>`. Jede Seite speichert vom Token, das sie herausgegeben hat, nur den SHA-256-Hash.

### Partnerschaft

| Methode | Pfad | Zweck |
|---|---|---|
| `GET` | `/api/federation/v1/partnership` | Status, Rabatte, Name/Anschrift (auch Verbindungstest) |
| `POST` | `/api/federation/v1/partnership/confirm` | `{"decision": "accept"|"decline", "discount": {"action": "accept"|"propose", "granted": …, "received": …}}` |
| `POST` | `/api/federation/v1/partnership/discount` | `{"action": "propose"|"accept"|"reject", "granted": …, "received": …}` |
| `POST` | `/api/federation/v1/partnership/end` | Partnerschaft beenden. Danach antworten alle Endpunkte mit `403` |

### Material und Techniker

| Methode | Pfad | Zweck |
|---|---|---|
| `GET` | `/api/federation/v1/articles?from=Y-m-d&to=Y-m-d&q=&page=` | Vermietbare Artikel mit `total`, `available` (im Zeitraum), `daily_price`, `discounted_daily_price` |
| `GET` | `/api/federation/v1/technicians` | `allowed`, `technicians` (`id`, `name`), `trades` |
| `POST` | `/api/federation/v1/requests` | Neue Anfrage. Body: `reference` (UUID, auf beiden Seiten gleich), `kind` (`material`/`technician`), `event_name`, `start_date`, `end_date`, `event_location`, `note`, `contact_name`, `contact_email`; bei Material zusätzlich `items` (`article_id`, `quantity`), bei Technikern `trades`, `technician_count`, `preferred_technician_id`. Wiederholtes Senden derselben `reference` liefert den bestehenden Stand |
| `GET` | `/api/federation/v1/requests/{reference}` | Aktueller Stand einer Anfrage des Absenders |
| `POST` | `/api/federation/v1/requests/{reference}/cancel` | Anfragender storniert |
| `POST` | `/api/federation/v1/requests/{reference}/status` | Anbieter meldet eine Entscheidung (`status`, `message`, `items`, `technicians`, `discount_percent`) |

Antwort auf Anfrage-Endpunkte: `reference`, `kind`, `status` (`pending`, `accepted`, `declined`, `cancelled`), `message`, `discount_percent`, `items`, `technicians`.

Ist der Partner beim Senden einer Entscheidung nicht erreichbar, wird das an der Anfrage vermerkt. Die Antwort kann dann erneut gesendet werden (*Antwort erneut an Partner senden*), oder der Anfragende fragt den Stand selbst ab.
