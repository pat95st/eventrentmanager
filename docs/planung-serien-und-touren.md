# Umsetzungsplan: Wiederkehrende Veranstaltungen (Serien) und Touren

Stand: 2026-11-18. Ergebnis der Anforderungsklärung (Fragen F, S, T, M, K, W, R, C).
Eine „Veranstaltung“ ist im Code eine Vermietung (`rentals`).

Umsetzung in drei Etappen; jede Etappe ist für sich auslieferbar und bekommt
eigene Migrationen (inkl. Übernahme in `database/schema.sql` und
`schema_migrations`-Liste, kein `USE`), Unit-Tests und einen CHANGELOG-Eintrag.

---

## Umsetzungsstand

- **Etappe 1 (Serien): umgesetzt** (Migration 176). Abweichungen vom Plan:
  - Bereich „Erinnerungen“ entfällt: `rental_reminders` protokolliert nur
    bereits verschickte Erinnerungen; automatische Erinnerungen ergeben
    sich ohnehin aus Ausgabe-/Rückgabezeit jedes Termins.
  - Bereich „Audio“ entfällt vorerst: Audio-Dateien liegen als Dateien im
    Speicher und gehören einer Veranstaltung; ein Kopieren der Verweise
    würde beim Löschen eines Termins die Dateien der anderen zerstören.
    Der Jingle-Verweis im kopierten Minutenprogramm wird daher geleert.
  - Techniker werden über `AssignTechnician` neu angefragt (mit der
    üblichen Benachrichtigung je Termin).
- **Etappe 2 (Touren): umgesetzt** (Migration 177). Abweichungen vom Plan:
  - Stromplanung je Stop entfällt vorerst: die Stromplanung hängt
    (Primärschlüssel, Anschlüsse des Veranstaltungsorts) an der
    Veranstaltung; eine Tour hat eine gemeinsame Stromplanung. Ein Umbau des
    Strommoduls auf Stops wäre ein eigenes Paket.
  - Material-Abweichungen je Stop nur auf Artikel-Ebene (Bundles/Sets
    werden in der Grundliste ohnehin in Artikel aufgelöst).
  - Ausgabe: `rental_items.tour_extra_quantity` = größter Zusatz eines
    Stops je Artikel; die Ausgabe-Checkliste erlaubt Grundmenge + Zusatz.
    Wann Zusatzmaterial physisch geladen wird, steht in den Ladelisten.
  - Wer an Stops nicht mehr gebraucht wird, erhält eine einfache E-Mail
    (keine Messenger-Nachricht).
  - Kalender: Monats- und Listenansicht zeigen die Stops des Tages unter
    der Tour; Wochenansicht und CalDAV zeigen die Tour als einen Eintrag.
- **Etappe 3 (Route/Fahrzeuge): umgesetzt** (Migration 178). Abweichungen vom Plan:
  - Start und Ziel werden nicht als eigene Spalten an `rentals` gespeichert,
    sondern als Tour-Stops mit `kind = 'start' | 'end'` (gleiche
    Adressfelder wie Zwischenstopps); ohne Eintrag gelten Ausgabe- bzw.
    Rückgabelager.
  - Stops ohne gespeicherten Veranstaltungsort (Freitext) haben keine
    Koordinaten und werden in der Route übersprungen (mit Hinweis).
  - Die Beladungsliste übernimmt geplante Fahrzeuge noch nicht als
    Vorschlag.
  - Leaflet 1.9.4 liegt lokal unter `public/assets/vendor/leaflet/`, die
    Kartenkacheln kommen von tile.openstreetmap.org.

## Gemeinsame Basis (Teil von Etappe 1)

### Übernahme-Dienst `RentalCopier`
Zentraler Baustein, den Serien (Erzeugen, „aus vorherigem übernehmen“,
„auf folgende übertragen“) und später Tour-Serien nutzen.

- Eingabe: Quell-Vermietung, Ziel-Vermietung(en), Bereiche (Enum
  `CopyScope`): `MATERIAL` (rental_items, rental_bundles +
  rental_bundle_allocations, rental_set_bookings + rental_set_allocations),
  `LOCATION` (venue_id, event_location, transport_note), `LOGISTICS`
  (Lager + relative Zeiten), `VEHICLES` (geplante Fahrzeuge, ab Etappe 3),
  `TECHNICIANS` (rental_technicians als neue Anfragen, rental_trades),
  `MINUTE_PROGRAM`, `POWER_PLAN`, `REMINDERS` (relativ), `AUDIO` (optional),
  `TOUR_STOPS` (ab Etappe 2).
- Standard-Auswahl: alles außer `AUDIO`.
- Nie kopiert: rental_issued_items, rental_vehicle_loads, Unterschriften,
  Checklisten-/Organizer-Token, Angebote, Auftragsbestätigungen, Rechnungen.
- Modus **Ersetzen**: gewählte Bereiche im Ziel werden ersetzt.
  `preview()` liefert eine Vorher/Nachher-Differenz für die Bestätigungsseite.
- Ziele mit bereits ausgegebenem Material werden übersprungen und gemeldet.
- Relative Zeiten: Abstand von `pickup_at`/`return_at` zu `start_date`/`end_date`
  sowie Dauer (`end_date - start_date`, `deployment_days`) werden aus der
  Quelle berechnet und auf das Zieldatum angewandt.

### Verfügbarkeitsprüfung (Warnung)
Heute prüft `AddRentalItemHandler` nur die Gesamtzahl der Inventarnummern.
Neuer Query `FindMaterialConflicts(rentalId | dateRange + items)`:
geplante Mengen aller sich zeitlich überschneidenden Vermietungen
(Logistikzeitraum `pickup_at`–`return_at`, sonst `start_date`–`end_date`)
gegen aktive Inventarnummern. Ergebnis nur als Warnung, nie blockierend.
Wird in Serien-Vorschau, Serien-Übersicht, Tour-Stops und auf der
Vermietungs-Detailseite angezeigt.

---

## Etappe 1: Serien (wiederkehrende Veranstaltungen)

### Datenmodell (Migration `176_rental_series.sql`)
```
rental_series
  id, name_template (NULL = Name der Ursprung-Vermietung),
  recurrence_type ENUM('weekly','monthly_day','monthly_weekday','yearly','manual'),
  interval_n INT, weekdays SET/VARCHAR (für weekly),
  month_day TINYINT, week_of_month TINYINT (1–5, -1 = letzter), weekday TINYINT,
  ends_on DATE NULL, occurrence_count INT NULL   -- genau eines Pflicht
  source_rental_id, created_at, updated_at

rentals
  + series_id CHAR(36) NULL  FK ON DELETE SET NULL
  + series_index INT NULL     -- laufende Nummer, für {nr}
  + series_detached_fields / series_customized TINYINT -- von Hand angepasst
```
Obergrenze: 100 Termine bzw. 2 Jahre pro Erzeugungsvorgang.

### Domain / Application
- `Domain\RentalSeries\RecurrenceRule` (Value Object) + `RecurrenceCalculator`
  (reine Funktion, gut testbar: Monatsenden, 5. Wochentag, Schaltjahr,
  Sommerzeit bei Logistikzeiten).
- Commands: `CreateSeriesFromRental` (Vorschau-Daten mit pro-Termin-Overrides
  für Datum, Dauer, Ort, gestrichen), `ExtendSeries`, `ChangeSeriesRule`
  (nur zukünftige, nicht ausgegebene, nicht angepasste Termine),
  `DetachRentalFromSeries`, `EndSeries(deleteFutureUnissued: bool)`,
  `CopyFromPreviousOccurrence(scopes)`, `PropagateToFollowingOccurrences(scopes)`.
- Namens-Platzhalter: `{datum}`, `{nr}`, `{jahr}`.

### UI
- Vermietungs-Detailseite: Knopf „Wiederkehrend machen“ → Formular Rhythmus
  + Ende → **Vorschau-Tabelle** (Datum, Dauer, Ort, Konflikt-Warnung,
  streichen) → Anlegen.
- Am Serientermin: „Aus vorherigem Termin übernehmen“ und
  „Auf folgende Termine übertragen“ mit Bereichs-Checkboxen und
  Vorher/Nachher-Vorschau.
- Serien-Übersichtsseite: alle Termine mit Datum, Ort, Status, Warnungen;
  Aktionen Verlängern, Rhythmus ändern, Termin lösen, Serie beenden.
- 🔁-Kennzeichen in Vermietungsliste und Kalender (`ListRentalsForCalendar`).
- Abrechnung unverändert pro Termin.

### Tests
RecurrenceCalculator (alle Rhythmen, Randfälle), RentalCopier (jeder Bereich,
Ausschlüsse, Überspringen begonnener Termine, relative Zeiten),
Serien-Handler, FindMaterialConflicts, SchemaSnapshotTest.

---

## Etappe 2: Touren

Eine Tour ist **eine** Vermietung mit `is_tour = 1` und mehreren Stops.

### Datenmodell (Migrationen `177_rental_tours.sql`, `178_tour_overrides.sql`)
```
rentals + is_tour TINYINT NOT NULL DEFAULT 0

rental_tour_stops
  id, rental_id FK CASCADE, position INT,
  kind ENUM('stop','waypoint'),
  -- Stop:
  venue_id NULL, location_text NULL,
  setup_at, event_start_at, event_end_at, teardown_end_at (DATETIME),
  contact_name, contact_phone, note,
  -- Zwischenstopp:
  waypoint_purpose ENUM('load','overnight','break','other') NULL,
  address_type ENUM('warehouse','company','saved','custom') NULL,
  warehouse_id NULL, saved_address_id NULL,
  street, postal_code, city, country, latitude, longitude,
  arrive_at NULL, depart_at NULL

tour_saved_addresses (id, label, street, postal_code, city, country, lat, lng)

rental_tour_stop_items           -- Material-Abweichungen je Stop
  id, stop_id FK CASCADE, article_id | bundle_article_id | set_id,
  delta INT (positiv = zusätzlich, negativ = weniger),
  allocation ENUM('whole_tour','stops_only') DEFAULT 'stops_only',
  issue_waypoint_id NULL, return_waypoint_id NULL

rental_tour_stop_technicians     -- Besetzungs-Abweichungen je Stop
  id, stop_id FK CASCADE, action ENUM('replace','add','remove'),
  user_id, replaces_user_id NULL, status (wie rental_technicians), ...

minute_programs      + tour_stop_id NULL FK CASCADE
rental_power_plans   PK (rental_id) → eigene id + tour_stop_id NULL
rental_power_assignments analog
```
`whole_tour`-Abweichungen fließen in die Gesamtbelegung und Packliste der
Tour; `stops_only`-Abweichungen belegen Material nur vom ersten bis zum
letzten betroffenen Stop (bzw. vom zugeordneten Lager-Zwischenstopp bis
zur Rücknahme).

### Funktionen
- Effektive Materialliste je Stop = Grundliste + Abweichungen
  (`TourStopMaterialResolver`), Konfliktprüfung je Zeitfenster.
- Sammelaktion Material und Techniker: Dialog mit Stop-Checkboxen,
  Schnellwahlen „nur dieser“, „dieser und alle folgenden“,
  „alle nicht begonnenen“, „alle“; vergangene Stops gesperrt.
- Techniker-Vertretung: Anfrage über bestehenden `TechnicianNotifier`
  (Zusage/Absage, Erinnerungen) mit Stop-Bezug; ersetzter Techniker erhält
  Info; Warnung am Stop bis zur Zusage.
- Ausgabe/Rücknahme an Lager-Zwischenstopps für `stops_only`-Material
  (Erweiterung der Ausgabe-/Rückgabe-Checklisten um Stop-Kontext).
- Ladeliste je Stop und Differenzliste („an Stop 3 zusätzlich laden /
  nach Stop 3 abladen“) als Ansicht und PDF; Tour-Packliste = Grundliste
  + `whole_tour`-Abweichungen.
- Stops nach Zeit sortiert; Zwischenstopps per Position „zwischen X und Y“,
  Uhrzeit optional.
- Kalender: Gesamtbalken + Stops darunter; Vermietungsliste: eine Zeile,
  aufklappbar; CalDAV-Export (`IcsEventWriter`) analog.
- `RentalCopier`: Bereich `TOUR_STOPS` (Stops inkl. Abweichungen, um den
  Serien-Rhythmus verschoben) → Touren können Serien sein.

### Tests
Resolver (Grundliste + Deltas, Bundles/Sets), Belegungszeiträume je
Allocation, Sammelaktions-Auswahl, Vertretungs-Workflow, Kopieren von
Touren in Serien, SchemaSnapshotTest.

---

## Etappe 3: Route und Fahrzeuge

### Datenmodell (Migration `179_tour_routing.sql`)
```
routing_settings (Singleton): provider 'openrouteservice', api_key,
  default_buffer_minutes DEFAULT 30
company_settings + latitude, longitude          (Geokodierung via Nominatim)
vehicles + height_cm, width_cm, length_cm, gross_weight_kg  (alle NULL)
rental_planned_vehicles (id, rental_id, vehicle_id, towing_vehicle_id NULL)
rentals + route_profile ENUM('car','hgv','hgv_vehicles') DEFAULT 'car',
          route_start_* / route_end_* (Typ + Referenz/Adresse, Standard Lager)
rental_route_cache (rental_id, hash der Wegpunkte+Profil, json, created_at)
```

### Funktionen
- `Infrastructure\Routing\OpenRouteServiceClient` (Directions, Profile
  `driving-car` / `driving-hgv` mit `restrictions` Höhe/Breite/Länge/Gewicht),
  Ergebnis gecacht über Hash der Wegpunkte, damit das Kontingent nicht
  bei jedem Seitenaufruf belastet wird.
- Wegpunkte: Start (Ausgabelager / Firma / gespeicherte / eigene Adresse)
  → Stops und Zwischenstopps in Reihenfolge → Ziel (Rückgabelager / …).
- LKW-Profil mit Fahrzeugdaten: strengste Werte über alle geplanten
  Fahrzeuge; Gespann = Länge und Gewicht addiert, Höhe/Breite Maximum.
  Fehlende Maße → Berechnung ohne diese Einschränkung + deutlicher Hinweis.
- Ansicht: Leaflet + OSM-Kacheln, Polyline, Marker je Stop/Zwischenstopp,
  Tabelle je Etappe mit Strecke und Fahrzeit.
- Plausibilitätswarnung: Abbau-Ende + Fahrzeit + Puffer > Aufbau-Beginn
  nächster Stop.
- Google-Maps-Link für die ganze Route (`/maps/dir/?api=1` mit
  `waypoints`); bei mehr Zwischenzielen als erlaubt in mehrere Links teilen.
- Einstellungsseite „Routenplanung“ (API-Key, Puffer) im Muster der
  bestehenden Integrations-Einstellungen; ohne Key: Karte mit Luftlinie +
  Google-Link als Rückfall.
- Fahrzeug-Formular um Maße und zulässiges Gesamtgewicht erweitern;
  geplante Fahrzeuge auf der Vermietung, die spätere Beladungsliste schlägt
  sie vor.

### Tests
Wegpunkt-Aufbau, Restriktions-Aggregation (inkl. Gespann, fehlende Werte),
Plausibilitätsprüfung, Google-Link-Aufteilung, ORS-Client mit
gemockter HTTP-Antwort, Cache-Invalidierung, SchemaSnapshotTest.

---

## Bewusst nicht im Umfang
- Sammelrechnung über mehrere Serientermine.
- Automatische Routenoptimierung (Reihenfolge ergibt sich aus den Terminen).
- Achslast-/Gefahrgut-Einschränkungen.
- Getrennte Routen je Fahrzeug.
