# Materialverleih Software

Lagerhaltung für einen Veranstaltungstechnik-Verleih: Artikel- und
Kategorienverwaltung, Hersteller, Inventurnummern und eine RMA-/
Wartungsverwaltung inkl. Dokumenten-Upload (Rechnungen, E-Mail-PDFs, …).

## Architektur

- **PHP** (>= 8.1), ohne Framework — ein schlanker Front-Controller
  (`public/index.php`) + eigener Router.
- **CQRS mit UseCases**: Jede Aktion ist ein eigener **Command** (schreibend)
  oder eine **Query** (lesend) mit genau einem **Handler**
  (`src/Application/<Bereich>/{Command,Query}/…`). Controller schicken
  Commands/Queries über einen simplen `CommandBus`/`QueryBus`
  (`src/Application/Command`, `src/Application/Query`) an die Handler.
- **Domain** (`src/Domain/...`): Entitäten und Repository-Interfaces,
  frei von Infrastruktur-Details.
- **Infrastructure** (`src/Infrastructure/...`): MariaDB-Repositories (PDO),
  lokaler Datei-Storage für Bilder/Dokumente, HTTP-Layer (Router, Request,
  Response, View, Controller).
- **MariaDB** als Datenbank, Schema in `database/schema.sql`.
- **Tailwind CSS** für die Oberfläche, lokal gebaut (kein CDN, siehe
  Hinweis unten) inkl. umschaltbarem Dark-Mode.

```
src/
  Domain/            Entitäten + Repository-Interfaces je Bereich
  Application/        Commands, Queries und ihre Handler (CQRS/UseCases)
  Infrastructure/
    Persistence/Pdo/   MariaDB-Repository-Implementierungen
    FileStorage/        Lokale Datei-Ablage für Bilder & RMA-Dokumente
    Http/               Router, Request/Response, Controller, Views-Bridge
resources/views/     PHP-Templates (Tailwind-Klassen)
database/schema.sql  Datenbankschema
storage/uploads/     Hochgeladene Artikelbilder & RMA-Dokumente (nicht in public/)
```

## Fachlicher Umfang

- **Kategorien**: Name + optionale Elternkategorie (beliebig tiefer Baum).
- **Hersteller**: eigene Verwaltung, im Artikel auswählbar, mit einem
  kurzen Präfix (z. B. „BOS“ für Bosch).
- **Artikel**: Name, Hersteller, Kategorie, Beschreibung, Gewicht,
  Abmessungen, optionaler Einkaufspreis, beliebig viele Bilder, ebenfalls
  mit einem kurzen Präfix (z. B. „AKS“ für Akkuschrauber). Auf der
  Artikel-Detailseite zusätzlich: kommende/vergangene Vermietungen, ein
  Verfügbarkeits-Ampelkalender (grün = frei, gelb = teilweise vermietet,
  rot = ausgebucht, je Tag) sowie eine **Zubehör**-Verwaltung (Zubehör ohne
  eigene Inventurnummer, nur Anzahl + Bezeichnung, z. B. „2× Netzkabel“).
- **Inventurnummern**: mehrere pro Artikel, je mit Anschaffungszeitpunkt und
  optional einem eigenen Einkaufspreis (falls abweichend vom Artikel). Die
  Nummer selbst wird standardisiert und automatisch vergeben:
  `(Hersteller-Präfix)-(Artikel-Präfix)-(laufende Nummer)`, z. B.
  `BOS-AKS-0001`. Die laufende Nummer zählt je Artikel hoch; eine manuelle
  Eingabe ist nicht mehr möglich. Jede Inventurnummer hat eine eigene
  Detailseite (Klick auf die Nummer) mit ihrer Vermietungshistorie.
- **Verkauf einzelner Artikel-Exemplare**: auf der Detailseite einer
  Inventurnummer lässt sich das Exemplar einzeln aus dem Bestand verkaufen
  — mit händisch angegebenem Verkaufspreis und optional zusätzlich
  abgerechneten Versandkosten. Der Kunde ist dabei optional (Barverkauf ohne
  hinterlegten Kunden ist möglich). Beim Verkauf wird automatisch eine
  echte Rechnung (Entwurf, Position „Verkauf Inventarnummer …“) mit
  derselben fortlaufenden Rechnungsnummern-Sequenz wie alle anderen
  Rechnungen angelegt. Eine einmal verkaufte Inventarnummer bleibt
  dauerhaft als Datensatz erhalten (kein Löschen mehr möglich), wird nie
  wieder neu vergeben und steht nicht mehr für die Vermietung/Ausgabe zur
  Verfügung — weder per Dropdown noch per Barcode-Scanner. In der
  Inventurnummern-Liste auf der Artikel-Detailseite werden bereits
  verkaufte Exemplare deutlich markiert („Verkauft am … für … €“) angezeigt.
- **RMA-Verwaltung**: pro Inventurnummer beliebig viele Wartungs-/
  RMA-Vorgänge mit Abschick- und Rücklieferdatum, Grund, Dienstleister,
  RMA-Referenz, Notizen sowie angehängten Dokumenten (PDF, Bilder, .eml).
  Der Status (Offen/Unterwegs/Abgeschlossen) wird aus den beiden Datumsfeldern
  abgeleitet, nie redundant gespeichert.
- **Kundenverwaltung** (eigener Menüpunkt „Kunden“): Art (Privat/Geschäftlich),
  Firmenname (nur bei Geschäftlich, dann Pflichtfeld), Vorname, Nachname,
  Straße/Hausnummer, Adresszusatz, PLZ, Ort, Telefon, E-Mail-Adresse.
  Liste mit Freitextsuche (Name/Firma/Ort/E-Mail) sowie separater
  Anlegen-/Bearbeiten-Formularseite.
- **Cases** (Menüpunkt „Cases“, im Untermenü von „Artikel“): ein Case ist
  ein Transport-/Flightcase-Modell, das funktional wie ein Artikel behandelt
  wird — mit Hersteller (aus derselben Herstellerliste wie Artikel), eigenem
  Präfix und Beschreibung. Von jedem Case-Modell gibt es beliebig viele
  physische **Exemplare**, jedes mit eigener, automatisch vergebener
  Inventurnummer nach demselben Schema wie bei Artikeln:
  `(Hersteller-Präfix)-(Case-Präfix)-(laufende Nummer)`, z. B. `BOS-RCK-0001`.
  Jedem einzelnen Case-Exemplar (nicht dem Case-Modell als Ganzes) können
  beliebig viele Artikel zugeordnet werden — auf **Artikel-Ebene mit Menge**
  (z. B. „5× Kabel XLR“), nicht gebunden an eine konkrete Inventurnummer des
  Artikels selbst. Wird derselbe Artikel einem Case-Exemplar erneut
  hinzugefügt, wird die Menge auf den bestehenden Eintrag addiert statt eine
  zweite Zeile anzulegen. Ein Hersteller lässt sich nicht löschen, solange er
  noch von einem Case verwendet wird; ein Artikel lässt sich nicht löschen,
  solange er noch in einem Case-Exemplar gepackt ist (jeweils analog zur
  bestehenden Sperre bei Artikeln).
- **Lieferanten** (eigener Menüpunkt „Lieferanten“): Name, Adresse
  (Straße/Hausnummer, PLZ, Ort), Telefon (optional), E-Mail (optional), eine
  optionale eigene Kundennummer bei diesem Lieferanten sowie eine
  Art-Unterscheidung „Kauf“/„Zumietung“. Jeder Inventurnummer eines Artikels
  kann optional ein Lieferant zugeordnet werden.
- **Fahrzeuge** (eigener Menüpunkt „Fahrzeuge“): Fuhrpark-Stammdaten,
  kategorisiert als „Auto“ oder „Anhänger“. Jedes Fahrzeug speichert
  Bezeichnung, Kennzeichen (optional), Zuladung (kg) und die Abmessung der
  Ladefläche (Freitext). Bei „Auto“ zusätzlich: ob eine Anhängekupplung
  vorhanden ist und, falls ja, die Anhängelast (kg). Bei „Anhänger“
  zusätzlich: das Leergewicht (kg). Ein Fahrzeug lässt sich nicht löschen,
  solange es noch in einer Beladungsliste oder einer Zugfahrzeug-Zuordnung
  verwendet wird.
- **Beladungsliste** (je Veranstaltung über „Beladungsliste“ auf der
  Veranstaltungs-Detailseite, `/veranstaltungen/{id}/beladung`): ordnet die
  bereits **ausgegebenen** Posten einer Veranstaltung (konkrete
  Inventarnummer, siehe Ausgabe-Checkliste) einzeln einem Fahrzeug zu — rein
  geplante Artikel+Menge-Posten ohne Ausgabe erscheinen hier bewusst nicht.
  Je Fahrzeug wird aus den Artikelgewichten (`Article::$weightValue`/
  `$weightUnit`) automatisch das Gesamtgewicht berechnet und gegen die
  Zuladung geprüft; fehlt ein Gewicht oder die Zuladung, wird das klar als
  „unbekannt“ statt als „nicht überladen“ angezeigt. Für Anhänger lässt
  sich zusätzlich ein ziehendes Auto zuordnen (nur Autos mit vorhandener
  Anhängekupplung werden zur Auswahl angeboten); dabei wird geprüft, ob
  Leergewicht + Beladung des Anhängers die Anhängelast des Zugfahrzeugs
  überschreitet. Die gesamte Berechnung steckt in der reinen, von der
  Datenbank unabhängigen Klasse `App\Domain\Vehicle\VehicleLoadCalculator`
  (plus `App\Domain\Article\WeightConverter` für die g/kg-Umrechnung).
- **Vermietung an Endkunden** (Menüpunkt „Vermietung“): eine Vermietung hat
  einen Kunden, Start-/Enddatum, Veranstaltungsname, Veranstaltungsort sowie
  editierbare Einsatztage (gedeckelt auf die Anzahl Tage zwischen Start- und
  Enddatum). Artikel werden **auf Artikel-Ebene mit Menge** geplant (analog
  zu Cases), noch ohne konkrete Inventurnummer. Für die tatsächliche
  **Ausgabe** gibt es eine eigene Checkliste (`/vermietungen/{id}/ausgabe`):
  dort wird das tatsächlich verliehene Material anhand der Inventarnummer
  erfasst — entweder per Dropdown oder über ein eigenes, autofokussiertes
  Barcode-Scanner-Eingabefeld (Inventarnummer scannen/eintippen, Enter),
  inklusive Sperre gegen doppelte/überlappende Vergabe derselben
  Inventarnummer an zwei sich zeitlich überschneidende Vermietungen. Hat ein
  Artikel Zubehör hinterlegt, muss dessen Vollständigkeit vor der Ausgabe in
  einem Modal bestätigt werden. Für die **Rückgabe** gibt es eine analoge
  Checkliste (`/vermietungen/{id}/rueckgabe`, ebenfalls per Dropdown oder
  Barcode-Scanner) inkl. Bestätigung eines Funktionstests je Rückgabe; ist
  der Funktionstest nicht erfolgreich, wird automatisch ein RMA-Vorgang für
  die betroffene Inventarnummer angelegt. Ein **Kalender** (`/kalender`)
  zeigt monatsweise, welches Material an welchem Tag unterwegs ist.
- **Kunden-Checkliste** (Vollständigkeitsprüfung beim Abbau): auf jeder
  Veranstaltungs-Detailseite lässt sich ein öffentlicher, geheimer Link
  erzeugen (`/kundencheckliste/{token}`), über den der Kunde — ganz ohne
  eigenes Benutzerkonto — die für ihn aktuell ausgegebenen Artikel selbst
  abhaken kann. Der Link kann manuell geteilt UND/ODER automatisch per
  E-Mail verschickt werden (Vorlauf + Ein/Aus-Schalter unter Einstellungen
  -> Erinnerungen, versendet über denselben Cronjob wie die Abhol-/
  Rückgabe-Erinnerungen, siehe `bin/send-rental-reminders.php`). Der Link
  wird automatisch ungültig, sobald alle Artikel der Veranstaltung
  zurückgegeben wurden. Die Bestätigungen des Kunden sind bewusst eine
  reine Kundenansicht und werden NICHT in der internen Rückgabe-Checkliste
  angezeigt.
- **Backup-Export/-Import**: unter „Einstellungen → Sicherung" lässt sich
  jederzeit eine vollständige ZIP-Sicherung herunterladen (gesamte
  Datenbank + alle Dateien aus `storage/uploads/`) und — nach einer
  deutlich markierten Sicherheitsabfrage, da das **unwiderruflich alle
  aktuellen Daten ersetzt** — auch wieder einspielen. Dieselbe
  Wiederherstellung steht alternativ auch direkt im Installations-
  Assistenten zur Verfügung (siehe „Setup" oben). Details siehe Abschnitt
  „Backup / Sicherung" unten.
- **Seitentitel**: jede Funktionsseite setzt einen passenden HTML-`<title>`
  nach dem Schema „(Funktionalität) - Materialverleih Verwaltung“
  (`View::page()`/`resources/views/layout.php`).

## Setup

**Empfohlener Weg für eine neue Installation: der Web-Installer.** Statt der
manuellen Schritte unten kann eine leere, bereits angelegte Datenbank (z. B.
über das Hosting-Panel erstellt) über `public/install.php` eingerichtet
werden — ähnlich dem Installer von WordPress. Der Assistent prüft die
Systemvoraussetzungen, nimmt die Datenbankzugangsdaten entgegen, spielt dabei
automatisch **alle** Migrationen aus `database/migrations/` der Reihe nach
ein (über `App\Infrastructure\Migrations\MigrationRunner`, idempotent — ein
Fehlschlag mitten im Lauf lässt sich nach Behebung einfach durch erneutes
Aufrufen von `install.php` fortsetzen) und legt anschließend einen
frei wählbaren Administrator-Zugang an (E-Mail/Passwort), statt des früher
fest hinterlegten Seed-Admins. Die Konfiguration wird automatisch unter
`storage/database.yaml` abgelegt (fällt der Installer beim Schreiben auf ein
Berechtigungsproblem, zeigt er den YAML-Inhalt zum manuellen Kopieren an).
Nach erfolgreichem Abschluss hinterlegt der Installer eine Sperrdatei
(`storage/installed.lock`) und verweigert danach jeden weiteren Aufruf —
ein erneutes Setup (z. B. um ein vergessenes Admin-Passwort zurückzusetzen)
ist nur durch gezieltes Löschen dieser Datei auf Dateisystemebene möglich.
Ruft man `public/` unkonfiguriert auf (kein `storage/database.yaml`
vorhanden), leitet die Anwendung automatisch zu `/install.php` weiter.
In Schritt 2 lässt sich zudem optional eine **SSL-CA-Zertifikatsdatei**
hochladen, um die Datenbankverbindung verschlüsselt (TLS/SSL) aufzubauen —
siehe Abschnitt „DB-Zugangsdaten konfigurieren" weiter unten für Details.

**Alternative in Schritt 2: aus einer Sicherung wiederherstellen.** Statt
die Datenbank frisch einzurichten, kann im Installer stattdessen eine zuvor
unter „Einstellungen → Sicherung" exportierte Backup-ZIP hochgeladen werden
(z. B. bei einem Server-Umzug) — siehe Abschnitt „Backup / Sicherung"
unten. Enthält die Sicherung bereits eigene Administrator-Zugänge, fragt
der Assistent danach, ob zusätzlich noch ein weiterer angelegt werden soll.

**Hinweis zur Testabdeckung:** `MigrationRunner` und die Installer-Logik
(Konfigurationsdatei schreiben/lesen, Admin-Anlage) wurden ausführlich gegen
SQLite sowie per Lint/Regex-Prüfung gegen alle realen Migrationsdateien
getestet; ein vollständiger Durchlauf aller Migrationen gegen einen echten
MariaDB-/MySQL-Server konnte in der Entwicklungsumgebung jedoch nicht
durchgeführt werden (kein DB-Server verfügbar). Vor dem produktiven Einsatz
daher einmal gegen eine Test-/Wegwerf-Datenbank durchlaufen lassen.

**Künftige Migrationen bei einer bestehenden Installation:** statt die
Dateien weiterhin einzeln per Hand mit `mysql -u root -p < ...` einzuspielen
(siehe die Liste unten, die aus historischen Gründen weiterhin funktioniert),
kann ab jetzt einfach

```bash
php bin/migrate.php
```

ausgeführt werden — zieht automatisch alle noch fehlenden Migrationen aus
`database/migrations/` nach (nutzt denselben `MigrationRunner` wie der
Installer, über die bestehende `storage/database.yaml`-Konfiguration).

---

**Manueller Weg (alternativ, z. B. für eine komplett neue Installation ohne
den Installer):**

1. **Datenbank anlegen**

   ```bash
   mysql -u root -p < database/schema.sql
   ```

   (Das Skript legt die Datenbank `materialverleih` selbst an.)

   **Bestehende Installation aktualisieren:** Wer die Software bereits im
   Einsatz hat (vor Einführung der Hersteller-/Artikel-Präfixe für
   Inventurnummern), führt stattdessen die Migration aus, statt die
   Datenbank neu anzulegen:

   ```bash
   mysql -u root -p materialverleih < database/migrations/001_add_prefixes.sql
   ```

   Vorher unbedingt ein Backup anlegen. Bestehende Hersteller und Artikel
   haben danach zunächst kein Präfix; die Anwendung fordert es beim
   nächsten Speichern über „Hersteller“ bzw. „Artikel bearbeiten“ ein.
   Bereits vergebene Inventurnummern bleiben unverändert nutzbar, auch
   wenn sie (noch) nicht dem neuen Format entsprechen.

   Wer die Software bereits **vor Einführung der Kundenverwaltung** im
   Einsatz hat, führt zusätzlich diese Migration aus (legt nur die neue
   Tabelle `customers` an, keine bestehenden Daten betroffen):

   ```bash
   mysql -u root -p materialverleih < database/migrations/002_add_customers.sql
   ```

   Wer die Software bereits **vor Einführung der Cases** im Einsatz hat,
   führt zusätzlich diese Migration aus (legt nur die drei neuen Tabellen
   `case_items`, `case_inventory_numbers` und `case_content_items` an, keine
   bestehenden Daten betroffen):

   ```bash
   mysql -u root -p materialverleih < database/migrations/003_add_cases.sql
   ```

   Wer die Software bereits **vor Einführung der Lieferantenverwaltung** im
   Einsatz hat, führt zusätzlich diese Migration aus (legt die Tabelle
   `suppliers` an und ergänzt `inventory_numbers` um die optionale Spalte
   `supplier_id`):

   ```bash
   mysql -u root -p materialverleih < database/migrations/004_add_suppliers.sql
   ```

   Wer die Software bereits **vor Einführung der Vermietung** im Einsatz
   hat, führt zusätzlich diese Migration aus (legt die drei neuen Tabellen
   `rentals`, `rental_items` und `rental_issued_items` an, keine
   bestehenden Daten betroffen):

   ```bash
   mysql -u root -p materialverleih < database/migrations/005_add_rentals.sql
   ```

   Wer die Software bereits **vor Einführung der Einkaufspreise/Kundennummer**
   im Einsatz hat, führt zusätzlich diese Migration aus (ergänzt
   `suppliers.customer_number`, `articles.purchase_price` und
   `inventory_numbers.purchase_price`, keine bestehenden Daten betroffen):

   ```bash
   mysql -u root -p materialverleih < database/migrations/006_add_prices_and_customer_number.sql
   ```

   Wer die Software bereits **vor Einführung von Artikel-Zubehör und der
   Rückgabe-Checkliste** im Einsatz hat, führt zusätzlich diese Migration aus
   (legt die neue Tabelle `article_accessories` an und ergänzt `rental_items`
   um `accessory_checked` sowie `rental_issued_items` um `returned_at`/
   `function_test_passed`, keine bestehenden Daten betroffen):

   ```bash
   mysql -u root -p materialverleih < database/migrations/007_add_accessories_and_returns.sql
   ```

   Wer die Software bereits **vor Einführung der Benutzerverwaltung/
   Authentifizierung** im Einsatz hat, führt zusätzlich diese Migration aus
   (legt die neuen Tabellen `users`, `login_challenges` und `smtp_settings`
   an und seedet einen Standard-Administrator, keine bestehenden Daten
   betroffen):

   ```bash
   mysql -u root -p materialverleih < database/migrations/008_add_users_and_auth.sql
   ```

   **Wichtig:** Ab dieser Migration verlangt die Anwendung einen Login (siehe
   Abschnitt „Anmeldung / Benutzerverwaltung“ unten). Der seed-Administrator
   (E-Mail `admin@materialverleih.local`, Passwort `ChangeMe!2026`) muss nach
   dem ersten Login umgehend durch ein eigenes Passwort ersetzt werden — die
   Anwendung erzwingt das ohnehin (`must_change_password`), aber die
   Zugangsdaten sind öffentlich bekannt und dürfen nicht produktiv bestehen
   bleiben.

   **Weitere Migrationen für bereits bestehende Installationen:** jede
   nachträglich eingeführte Funktion bringt ihre eigene, nummerierte
   Migration mit, die bei einem Update **manuell** nachgezogen werden muss
   (die Anwendung führt keine Migrationen automatisch aus). Nach einem
   `git pull`/Code-Update immer prüfen, welche der folgenden Dateien noch
   nicht eingespielt wurden, und sie **der Reihe nach** ausführen:

   ```bash
   mysql -u root -p materialverleih < database/migrations/009_add_electrical_inspection_flag.sql
   mysql -u root -p materialverleih < database/migrations/010_add_electrical_inspections.sql
   mysql -u root -p materialverleih < database/migrations/011_add_venues.sql
   mysql -u root -p materialverleih < database/migrations/012_add_billing_fields.sql
   mysql -u root -p materialverleih < database/migrations/013_add_price_tiers.sql
   mysql -u root -p materialverleih < database/migrations/014_make_event_location_nullable.sql
   mysql -u root -p materialverleih < database/migrations/015_add_company_settings.sql
   mysql -u root -p materialverleih < database/migrations/016_add_legal_settings.sql
   mysql -u root -p materialverleih < database/migrations/017_add_webauthn_credentials.sql
   mysql -u root -p materialverleih < database/migrations/018_add_user_default_page_size.sql
   mysql -u root -p materialverleih < database/migrations/019_add_user_locale.sql
   mysql -u root -p materialverleih < database/migrations/020_add_label_printers.sql
   mysql -u root -p materialverleih < database/migrations/021_add_offers.sql
   mysql -u root -p materialverleih < database/migrations/022_add_company_logo_options.sql
   mysql -u root -p materialverleih < database/migrations/023_add_user_names_password_setup_and_two_factor_enforcement.sql
   mysql -u root -p materialverleih < database/migrations/024_add_venue_power_connections.sql
   mysql -u root -p materialverleih < database/migrations/025_add_user_sessions.sql
   mysql -u root -p materialverleih < database/migrations/026_add_company_brand_name.sql
   mysql -u root -p materialverleih < database/migrations/027_add_smtp_reply_to_and_archive_bcc.sql
   mysql -u root -p materialverleih < database/migrations/028_add_email_templates.sql
   mysql -u root -p materialverleih < database/migrations/029_add_smtp_archive_categories.sql
   mysql -u root -p materialverleih < database/migrations/030_add_offer_pdf_template.sql
   ```

   **Weitere, neuere Migrationen (031–063)** ergänzen u. a. Rechnungen,
   Preisstaffeln, CalDAV-Kalender und API-Tokens — siehe `database/migrations/`
   für die vollständige, fortlaufend nummerierte Liste; jede bestehende
   Installation zieht beim Update alle noch fehlenden Dateien in
   aufsteigender Reihenfolge nach.

   **Migration 064 (`database/migrations/064_convert_pdf_settings_to_rich_html.sql`)
   ist für jede bestehende Installation mit bereits gepflegten
   Angebots-/Rechnungsvorlagen-Texten Pflicht**, sobald die neue
   Rich-Text-Bearbeitung (fett/kursiv/unterstrichen/Überschriften/Listen/
   Ausrichtung, ähnlich Word/LibreOffice Writer) für die PDF-Einleitungs-/
   Schlusstexte genutzt werden soll: sie benennt `intro_markdown`/
   `closing_markdown` in `intro_html`/`closing_html` um (Speicherformat
   wechselt von Markdown zu einem vom neuen Editor gelieferten HTML-Fragment,
   siehe `App\Infrastructure\RichText\HtmlSanitizer`). Bestehende Texte gehen
   dabei nicht verloren, erscheinen aber zunächst als ein einzelner,
   unformatierter Absatz und können danach im neuen Editor bei Bedarf manuell
   nachformatiert werden:

   ```
   mysql -u root -p materialverleih < database/migrations/064_convert_pdf_settings_to_rich_html.sql
   ```

   Kurzüberblick, was jede davon bringt (Details im jeweiligen Datei-Kopf):

   | Migration | Funktion |
   |---|---|
   | 009 | Flag „Elektroprüfung nötig“ je Artikel |
   | 010 | Tabelle für Elektroprüfungen (DGUV V3) je Inventurnummer |
   | 011 | Veranstaltungsorte (Stammdaten, optional mit Karte) |
   | 012 | Abrechnungs-Flag + Preis pro Einsatztag je Artikel |
   | 013 | Preisstaffelung (Aufschlag % je Einsatztage) |
   | 014 | Veranstaltungsort-Feld optional (ein Feld statt zwei) |
   | 015 | Firmeneinstellungen (Name/Adresse/Logo) |
   | 016 | Vermietbedingungen/Impressum/Datenschutzerklärung |
   | 017 | Passkey-/WebAuthn-Anmeldung |
   | 018 | Standard-Seitengröße für paginierte Listen (pro Benutzer) |
   | 019 | Sprachpräferenz Deutsch/Englisch (pro Benutzer) — **ohne diese Migration bleibt der Sprachumschalter „DE \| EN“ im Header wirkungslos** |
   | 020 | Etikettendrucker-Verwaltung (Brother P-touch u. a.) |
   | 021 | Angebote (Kostenvoranschläge), unabhängig von Veranstaltungen |
   | 022 | Logo-Optionen: maximale Höhe im Header, optionales Dark-Mode-Logo |
   | 023 | Vor-/Nachname je Benutzer, passwortloses Anlegen mit Passwort-Einrichtungslink, erzwungene 2FA-Einrichtung |
   | 024 | Stromanschlüsse je Veranstaltungsort (Typ, Leitungsschutzschalter, RCD-Auslösestrom, Ort) + optionaler Hausmeister-Kontakt |
   | 025 | Aktive Sitzungen je Benutzer (Geräte-/Browser-Info, einzeln oder gesammelt beendbar) |
   | 026 | Optionaler Markenname in den Firmeneinstellungen (Alt-Text des Logos) |
   | 027 | SMTP: optionale Reply-To-Adresse + optionale Archivierungsadresse (BCC bei jeder ausgehenden Mail) |
   | 028 | Admin-bearbeitbare E-Mail-Vorlagen (Betreff + HTML-Inhalt) für alle vom System versendeten Mails |
   | 029 | SMTP: Steuerung, welche Mail-Kategorien bei gesetzter Archivierungsadresse tatsächlich per BCC archiviert werden |
   | 030 | Admin-bearbeitbarer Einleitungs-/Schlusstext im Angebots-PDF-Dokument selbst |
   | 031 | Rechnungseinstellungen (Kleinunternehmer-Regelung, Steuersatz, Rechnungsnummern-Präfix, Zahlungsziel, Bankverbindung) + Sequenz-Tabelle für die fortlaufende Rechnungsnummer |
   | 032 | Admin-bearbeitbarer Einleitungs-/Schlusstext im Rechnungs-PDF-Dokument selbst |
   | 033 | Rechnungen (unabhängig von Angeboten und Veranstaltungen, optional aus beiden erstellbar) |
   | 034 | Admin-bearbeitbare "Rechnung per E-Mail"-Vorlage + neue Mail-Archivierungskategorie dafür |
   | 035 | Belege (eingehende Lieferantenrechnungen/-quittungen als PDF-Upload, je einem Lieferanten zugeordnet) |
   | 036 | Optionale Verknüpfung von Inventurnummern mit einem Beleg (inventory_numbers.receipt_id), sodass mehrere Inventurnummern denselben Lieferanten über einen gemeinsamen Beleg beziehen können, statt ihn einzeln anzugeben |
   | 037 | Preis-Einstellungen (Zubehörpauschale, gilt für Angebote UND Rechnungen) + Zubehörpauschale-Snapshot/Kombirabatt-Spalten auf invoices |
   | 038 | Zubehörpauschale-Snapshot/Kombirabatt-Spalten auf offers (analog zu Migration 037) |
   | 039 | Verbindungseinstellungen für den Beleg-Posteingang (E-Mail-Postfach, IMAP/POP3) |
   | 040 | Allow-Liste der Absenderadressen für den Beleg-Posteingang |
   | 041 | Eingesammelte Beleg-Posteingang-Nachrichten und ihre Anhänge |
   | 042 | Optionale Benutzer-Benachrichtigung per E-Mail bei neuen Beleg-Posteingang-Nachrichten |
   | 043 | VAPID-Schlüssel/Einstellungen + Browser-Push-Subscriptions (Web Push, zusätzlich zur E-Mail-Benachrichtigung bei neuen Beleg-Posteingang-Nachrichten) |
   | 044 | Ziellaufwerke (externe Speicherziele: WebDAV/Nextcloud, FTPS, SFTP, SCP, S3) für den automatischen Dokument-Push |
   | 045 | Zuordnung Dokumenttyp (Rechnung/Angebot/Packliste/Beleg) -> Ziellaufwerk(e) + Ablagepfad-Vorlage |
   | 046 | Protokoll jedes automatischen Dokument-Pushs (Erfolg/Fehlschlag je Ziellaufwerk) |
   | 047 | Reparatur-Art (`kind`: extern/eigenreparatur) + Arbeitszeit (`worked_hours`) bei RMA-Vorgängen |
   | 048 | Verknüpfung von Belegen (Ersatzteil-Einkäufen) zu einem RMA-Vorgang (`rma_receipts`) |
   | 049 | Stammdaten-Katalog für abrechenbare Arbeitszeit-Typen (`labor_time_articles`) |
   | 050 | Arbeitszeit-Positionen eines Angebots (`offer_labor_items`) |
   | 051 | Arbeitszeit-Positionen einer Rechnung (`invoice_labor_items`) |
   | 066 | Gemeinsames PDF-Design (Akzentfarbe/Logo-Position) für Angebot/Rechnung (`pdf_design_settings`) |
   | 067 | Automatische Abhol-/Rückgabe-Erinnerungen: Vorlauf-Einstellung + Versand-Log (`rental_reminder_settings`, `rental_reminders`) |
   | 072 | Verkauf einzelner Artikel-Exemplare aus dem Bestand (`sales`) + optionale Versandkosten auf Rechnungen (`invoices.shipping_amount`) |

   **Beleg-Posteingang per Cronjob (WICHTIG nach Migration 039–042):** der
   automatische Import läuft NICHT im Web-Request, sondern muss zusätzlich
   als Cronjob eingerichtet werden — `bin/import-receipt-emails.php`, siehe
   Einstellungen → Mailserver im Admin-Bereich für den auf dem jeweiligen
   Server tatsächlich gültigen Pfad und eine fertige Beispiel-Cronjob-Zeile.
   Ohne eingerichteten Cronjob (oder solange "Import aktivieren" nicht
   angehakt ist) bleibt der Posteingang leer, es passiert aber auch sonst
   nichts Schädliches.

   **Abhol-/Rückgabe-Erinnerungen per Cronjob (nach Migration 067):** erinnert
   Kunden automatisch per E-Mail an den Abhol- bzw. Rückgabetermin einer
   Vermietung (Voraussetzung: der jeweilige Termin ist unter "Logistik" bei
   der Vermietung hinterlegt, siehe `pickup_at`/`return_at`). Läuft NICHT im
   Web-Request, sondern muss als Cronjob eingerichtet werden —
   `bin/send-rental-reminders.php`, empfohlen **stündlich**, da der Vorlauf
   in Stunden konfiguriert wird (Einstellungen → Erinnerungen):

   ```
   0 * * * * /usr/bin/php /pfad/zum/projekt/bin/send-rental-reminders.php >> /pfad/zum/projekt/storage/logs/rental-reminders.log 2>&1
   ```

   Ohne eingerichteten Cronjob bleiben Vermietungen einfach unerinnert, es
   passiert sonst nichts Schädliches. Jede Erinnerungsart wird pro
   Vermietung nur einmal automatisch verschickt (siehe `rental_reminders`).

   Ein bequemer Weg, offene Migrationen zu finden: die Namen der bereits
   angelegten Tabellen mit `SHOW TABLES;` gegen die obige Liste abgleichen,
   oder einfach alle Dateien ab der zuletzt bekannten Nummer der Reihe nach
   ausführen — bereits vorhandene Spalten/Tabellen führen zu einem Fehler,
   den man dann für genau diese eine Datei ignorieren kann.

2. **DB-Zugangsdaten konfigurieren (außerhalb des Projekt-Wurzelverzeichnisses)**

   Die Zugangsdaten liegen bewusst **nicht** im Projektordner (der z. B. beim
   Shared Hosting oft als DocumentRoot/Vhost-Ziel dient), sondern in einer
   eigenen `database.yaml` daneben. Vorlage kopieren und **außerhalb** des
   Projektordners ablegen:

   ```bash
   cp database.example.yaml /usr/home/patricya/material_database.yaml
   # danach mit den echten Zugangsdaten befüllen
   ```

   Der feste Standardpfad ist in `src/bootstrap.php` hinterlegt:

   ```
   /usr/home/patricya/material_database.yaml
   ```

   Soll die Datei an einem anderen Ort liegen (z. B. auf einem anderen
   Deployment/einer anderen Umgebung), den absoluten Pfad stattdessen über
   die Umgebungsvariable `DATABASE_CONFIG_PATH` setzen — z. B. in der
   Apache-Vhost-Konfiguration (`SetEnv DATABASE_CONFIG_PATH /pfad/zur/database.yaml`)
   oder vor dem Start des Entwicklungsservers:

   ```bash
   export DATABASE_CONFIG_PATH=/pfad/zur/database.yaml
   ```

   Format von `database.yaml` (nur flache `schluessel: wert`-Zeilen, siehe
   `database.example.yaml`):

   ```yaml
   host: 127.0.0.1
   port: 3306
   database: materialverleih
   username: materialverleih_app
   password: "ein-geheimes-passwort"
   charset: utf8mb4
   ```

   **Optional: verschlüsselte (TLS/SSL) Verbindung zur Datenbank** — z. B. bei
   einer verwalteten MariaDB/MySQL-Instanz eines Hosting-Anbieters, oder wenn
   Datenbank und Webserver nicht auf derselben Maschine laufen. Dafür den
   Pfad zu einer CA-Zertifikatsdatei (PEM-Format, vom Anbieter bereitgestellt)
   zusätzlich in `database.yaml` eintragen:

   ```yaml
   ssl_ca: /pfad/zur/ca.pem
   ```

   Mit gesetztem `ssl_ca` wird die Verbindung verschlüsselt UND das
   Server-Zertifikat serverseitig gegen diese CA geprüft
   (`PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT`, siehe
   `App\Infrastructure\Persistence\Pdo\Connection`). Nur in Ausnahmefällen
   nötig (z. B. ein selbstsigniertes Zertifikat ohne zum Hostnamen passendem
   CN/SAN): diese Prüfung lässt sich einzeln abschalten, die Verbindung
   bleibt dabei trotzdem verschlüsselt:

   ```yaml
   ssl_verify_server_cert: false
   ```

   Fehlt `ssl_ca` (Standard), bleibt die Verbindung wie bisher
   unverschlüsselt — reine Opt-in-Funktion, bestehende Installationen sind
   nicht betroffen. Beim **Web-Installer** (siehe unten) lässt sich die
   CA-Zertifikatsdatei direkt im Browser hochladen; sie wird dann automatisch
   unter `storage/database-ssl-ca.pem` abgelegt und der Pfad in die neu
   geschriebene `database.yaml` eingetragen.

3. **Upload-Verzeichnis beschreibbar machen**

   ```bash
   chmod -R 775 storage/uploads
   ```

4. **Entwicklungsserver starten** (kein Composer/Build nötig — es gibt
   bewusst keine externen Abhängigkeiten, ein eigener Autoloader lädt
   `App\…`-Klassen aus `src/`):

   ```bash
   php -S localhost:8000 -t public router.php
   ```

   Wichtig ist das `-t public`: es setzt das Docroot des eingebauten
   Servers auf `public/`, damit statische Dateien (z. B. `public/assets/css/app.css`)
   auch tatsächlich von dort ausgeliefert werden. Ohne `-t public` sucht der
   eingebaute Server sie fälschlich im Projekt-Wurzelverzeichnis und liefert
   404, obwohl die Datei existiert.

   (`router.php` sorgt dafür, dass alle Pfade – außer echten Dateien unter
   `public/` – beim Front-Controller `public/index.php` landen; für
   Apache im Produktivbetrieb `public/` als DocumentRoot setzen, das
   mitgelieferte `public/.htaccess` übernimmt dort dasselbe.)

   Dann [http://localhost:8000](http://localhost:8000) öffnen (leitet auf
   `/artikel` weiter).

### Composer (optional)

`composer.json` deklariert nur das PSR-4-Autoload-Mapping (praktisch für
IDEs) — zum Ausführen der App wird **kein** `composer install` benötigt.
Sollen später echte Pakete ergänzt werden (z. B. `ramsey/uuid`,
PHPUnit, ein Tailwind-Build), einfach normal per Composer/NPM hinzufügen.

### Tailwind: lokal gebaut (kein CDN mehr)

Tailwind wird **nicht** mehr über das CDN-Skript eingebunden. Ausgeliefert
wird eine fertig kompilierte, lokale CSS-Datei unter
`public/assets/css/app.css`, die `resources/views/layout.php` und
`resources/views/errors/404.php` per `<link rel="stylesheet" href="/assets/css/app.css">`
einbinden. Zur Laufzeit besteht damit keinerlei Abhängigkeit mehr von
`cdn.tailwindcss.com` oder einem anderen externen Server — auf dem
Produktivserver (auch per Shared-Hosting) wird **kein Node.js/npm**
benötigt, die fertige CSS-Datei liegt bereits im Repository.

Quelle für einen Rebuild ist `resources/css/app.css` (Tailwind-Direktiven
`@tailwind base/components/utilities` + ein kleines `@layer base` für
Formularfelder) zusammen mit `tailwind.config.js` (u. a. `darkMode: 'class'`
und die eigene `brand`-Farbpalette). Sobald in den Templates neue
Tailwind-Klassen verwendet werden, die noch nicht in `public/assets/css/app.css`
enthalten sind, muss neu gebaut werden — auf einer Maschine mit Node.js:

```bash
npm install
npm run build:css
```

Das schreibt die aktualisierte, minifizierte CSS-Datei nach
`public/assets/css/app.css`. Für die aktuell ausgelieferte Version dieser
Datei stand in der Entwicklungsumgebung kein Zugriff auf npm/CDN-Server zur
Verfügung; sie wurde deshalb händisch anhand der offiziellen Tailwind-v3-
Design-Tokens für exakt die in den Templates verwendeten Klassen erzeugt
(funktional äquivalent zu einem echten `tailwindcss`-Build). Sobald neue
Utility-Klassen hinzukommen, sollte einmal reell mit `npm run build:css`
gebaut werden, damit garantiert alle Klassen (inkl. eventueller Sonderfälle)
korrekt abgedeckt sind.

### Dark-Mode

Ein Umschalt-Button (Sonne/Mond-Icon) oben rechts im Menü wechselt zwischen
hellem und dunklem Design. Technisch:

- `tailwind.config.js` nutzt `darkMode: 'class'` — der Dark-Mode wird also
  gezielt per CSS-Klasse `dark` auf `<html>` aktiviert, nicht nur anhand der
  Systemeinstellung.
- Die gewählte Einstellung wird in `localStorage` unter dem Schlüssel
  `theme` (`'dark'` oder `'light'`) gespeichert und bleibt so über Besuche
  hinweg erhalten.
- Ein kleines, blockierendes `<script>` direkt im `<head>` liest diesen
  Wert (fällt ohne gespeicherten Wert auf `prefers-color-scheme: dark`
  zurück) und setzt die `dark`-Klasse **bevor** die Seite sichtbar gerendert
  wird — so gibt es kein kurzes Aufblitzen im falschen Design beim Laden.
- Beim Klick auf den Toggle-Button wird die Klasse umgeschaltet und der neue
  Wert in `localStorage` gespeichert.

### Farbschema (Einstellungen)

Unter „Einstellungen" (Zahnrad-Icon oben rechts im Menü, Route `/einstellungen`)
lässt sich die Akzentfarbe der Oberfläche aus sechs Farbschemata wählen (Blau
[Standard], Grün, Violett, Orange, Rot, Türkis). Technisch nach demselben
Muster wie der Dark-Mode:

- Alle bisher fest verdrahteten `brand-*`-Farbwerte in
  `public/assets/css/app.css` wurden auf CSS-Custom-Properties (`--brand-50`
  bis `--brand-950`) umgestellt. Jedes Farbschema ist ein eigener
  `:root[data-scheme="…"]`-Block mit den passenden Werten; ohne Attribut
  (bzw. `data-scheme="blue"`) gilt weiterhin das bisherige Blau.
- Die Wahl wird in `localStorage` unter dem Schlüssel `colorScheme`
  gespeichert und per `data-scheme`-Attribut auf `<html>` gesetzt — das
  blockierende `<script>` im `<head>` (siehe Dark-Mode oben) setzt dieses
  Attribut ebenfalls schon vor dem ersten Rendern.
- Es gibt **keine** Speicherung in der Datenbank: da die Software keine
  Benutzerkonten hat, gilt die Wahl je Gerät/Browser, genau wie der
  Dark-Mode.

## Statische Analyse (PHPStan)

Das Projekt ist auf PHPStan **Level max** konfiguriert (`phpstan.neon`,
prüft `src/`). Da an vielen Stellen bewusst mit `mixed` gearbeitet wird
(Rückgabetyp `handle(): mixed` der CQRS-Handler, PDO-Fetch-Ergebnisse ohne
Generics-Stubs), erzeugt Level max auf dem bestehenden Code sehr viele
Meldungen, die keine echten Fehler sind, sondern aus diesem bewussten
Architektur-Stil folgen. Diese sind in `phpstan-baseline.neon`
eingefroren (1313 Meldungen zum Zeitpunkt der Erstellung), sodass eine
normale Prüfung **sauber durchläuft** und nur *neue* Probleme in
künftigen Änderungen auffallen.

Ausführen (PHPStan ist kein Composer-Dependency dieses Repos, siehe unten):

```bash
composer require --dev phpstan/phpstan
vendor/bin/phpstan analyse --configuration=phpstan.neon
```

Alternativ ohne Composer, per PHAR (z. B. von
`https://github.com/phpstan/phpstan/releases/latest/download/phpstan.phar`):

```bash
php phpstan.phar analyse --configuration=phpstan.neon
```

Baseline nach größeren Aufräumarbeiten neu erzeugen:

```bash
vendor/bin/phpstan analyse --configuration=phpstan.neon --generate-baseline=phpstan-baseline.neon
```

Das Analyse-Cache-Verzeichnis `var/phpstan/` wird automatisch angelegt und
ist nicht Teil des Repositories (`.gitignore`).

## Tests (Codeception)

Unit- und Acceptance-Tests liegen vollständig unter `tests/` (über Composer
installiert, siehe `composer.json` → `require-dev`). Kurzfassung:

```bash
composer install        # installiert Codeception + führt `codecept build` aus
composer test:unit      # Unit-Tests: keine Datenbank/Anwendung nötig
composer test:acceptance # Acceptance-Tests: braucht eine laufende Instanz + Test-Datenbank
```

Ausführliche Anleitung (insbesondere Einrichtung der Test-Datenbank für die
Acceptance-Suite): siehe [`tests/README.md`](tests/README.md).

## Anmeldung / Benutzerverwaltung

Die Anwendung verlangt seit Migration `008_add_users_and_auth.sql` einen
Login (E-Mail + Passwort, optional zweiter Faktor). Details zu Rechten,
2FA und SMTP-Versand siehe Projektdokumentation. Standard-Administrator nach
Neuinstallation bzw. Migration:

- E-Mail: `admin@materialverleih.local`
- Passwort: `ChangeMe!2026`

Dieses Passwort **muss** nach dem ersten Login geändert werden — die
Anwendung erzwingt dies automatisch, bevor irgendeine andere Seite
aufgerufen werden kann.

## Progressive Web App (PWA)

Die Anwendung ist als installierbare PWA nutzbar (z. B. für den Einsatz auf
Tablets/Smartphones beim Be-/Entladen oder bei der Ausgabe/Rücknahme vor
Ort):

- `public/manifest.webmanifest` — Name, Farben, Icons
  (`public/assets/img/icons/`, austauschbar gegen das eigene Firmenlogo,
  siehe `public/assets/img/icons/README.md`).
- `public/sw.js` — Service Worker, cacht **ausschließlich** die echt
  statischen Dateien (CSS, JS, Icons, Manifest) für schnellen und
  Offline-Zugriff auf das App-Shell. Alle dynamischen Inhalte (jede
  serverseitig gerenderte Seite inkl. CSRF-Token, alle API-Antworten sowie
  sämtliche Bilder — Firmenlogo, Hintergrundbild, Artikel-/RMA-Anhänge aus
  der Datenbank bzw. `storage/uploads/`) werden bewusst **nicht** gecacht
  und immer frisch vom Server nachgeladen. Der Worker wird bereits auf den
  Login-/2FA-/Passwort-Seiten registriert (siehe `resources/views/
  _pwa_head.php`), die PWA ist also schon vor der Anmeldung installierbar.
- Nach Änderungen an `app.css`/`barcode-scanner.js` die `CACHE_VERSION` in
  `public/sw.js` erhöhen, damit bereits installierte Geräte die neue Version
  zeitnah übernehmen (Stale-while-revalidate aktualisiert sonst erst beim
  übernächsten Aufruf im Hintergrund).
- PWA-Installation benötigt HTTPS (lokal ist `localhost` ausgenommen) — auf
  einem Produktivserver ohne gültiges TLS-Zertifikat bietet der Browser
  "Installieren"/"Zum Startbildschirm hinzufügen" nicht an, die Anwendung
  selbst funktioniert davon unabhängig aber ganz normal weiter.

## Backup / Sicherung

Unter „Einstellungen → Sicherung" (nur für Administratoren):

- **Export**: erzeugt eine ZIP-Datei mit einem vollständigen SQL-Dump aller
  Tabellen (`App\Infrastructure\Persistence\Pdo\MariadbDatabaseDumper`,
  Schema per `SHOW CREATE TABLE` + Daten als `INSERT`-Anweisungen — bewusst
  dasselbe Format, das sich wie eine Migration einspielen lässt) sowie
  einer 1:1-Kopie von `storage/uploads/` und bietet sie direkt zum Download
  an. **Enthält bewusst NICHT** `storage/database.yaml` (die
  Datenbank-Zugangsdaten der Quell-Installation) — ein Import erfolgt immer
  gegen eine bereits verbundene Zieldatenbank, deren eigene Zugangsdaten
  nicht überschrieben werden sollen.
- **Import**: spielt eine solche ZIP wieder ein — sowohl über die
  Admin-Oberfläche (erfordert das Eintippen des Bestätigungstexts „DATEN
  ERSETZEN") als auch alternativ im Installations-Assistenten (Schritt 2,
  „Stattdessen eine vorhandene Sicherung wiederherstellen"). **Ersetzt dabei
  vollständig** den Inhalt jeder im Dump enthaltenen Tabelle (`DROP TABLE` +
  `CREATE TABLE` + `INSERT`, kein Zusammenführen mit bestehenden Daten) und
  den kompletten Inhalt von `storage/uploads/` (alte, nicht im Backup
  enthaltene Dateien werden entfernt). Im Anschluss zieht
  `App\Infrastructure\Migrations\MigrationRunner` automatisch alle
  Migrationen nach, die zum Zeitpunkt der Sicherung noch nicht existierten —
  eine ältere Sicherung lässt sich damit auch auf einer neueren
  Anwendungsversion wiederherstellen.
- Zuständige Klassen: `App\Infrastructure\Backup\BackupArchiveBuilder`
  (Export) und `App\Infrastructure\Backup\BackupArchiveImporter` (Import),
  jeweils über `App\Domain\Backup\DatabaseDumperInterface` bzw.
  `DatabaseRestorerInterface` von der konkreten MariaDB-Implementierung
  entkoppelt.

**Hinweis zur Testabdeckung:** `BackupArchiveBuilder`/`BackupArchiveImporter`
(ZIP-Aufbau, Asset-Ersetzung, Zusammenspiel mit `MigrationRunner`) sowie die
Bestätigungstext-Prüfung von `ImportBackupHandler` wurden end-to-end gegen
Fake-Implementierungen von `DatabaseDumperInterface`/`DatabaseRestorerInterface`
getestet. Die konkrete SQL-Erzeugung/-Wiedereinspielung in
`MariadbDatabaseDumper`/`MariadbDatabaseRestorer` (`SHOW CREATE TABLE`,
mehrzeilige `INSERT`-Anweisungen) ließ sich dagegen mangels echtem
MariaDB-/MySQL-Server in dieser Entwicklungsumgebung nicht gegen eine echte
Datenbank verifizieren (siehe Testabdeckungs-Hinweis zum Installer oben) —
vor dem produktiven Einsatz einmal Export und Import gegen eine
Test-/Wegwerf-Datenbank durchspielen.

## Bekannte Grenzen / nächste Schritte

- Keine automatisierten Tests (PHPUnit) hinterlegt; die CQRS-Struktur
  (Command/Query + Handler mit injizierten Repository-Interfaces) ist
  bewusst so geschnitten, dass sich Handler leicht isoliert mit
  In-Memory-Repository-Fakes testen lassen.
- RMA-Dokumente liegen unter `storage/uploads/rma/<rma_id>/…`, Artikelbilder
  unter `storage/uploads/articles/<article_id>/…` — beides außerhalb von
  `public/`, der Zugriff läuft ausschließlich über die jeweiligen
  Controller-Routen (`/artikel-bilder/…`, `/inventurnummern/.../rma/.../dokumente/...`).
- Inventurnummern werden beim Anlegen fest aus den zu diesem Zeitpunkt
  gültigen Präfixen gebildet. Wird ein Präfix später geändert, ändern sich
  bereits vergebene Inventurnummern nicht rückwirkend mit.
- Beim Löschen eines kompletten Artikels werden die RMA-Dokumente seiner
  Inventurnummern aktuell nicht automatisch von der Platte entfernt
  (nur die DB-Zeilen kaskadieren). Für den produktiven Einsatz empfiehlt
  sich ein aufräumender Hintergrundjob oder eine Erweiterung von
  `DeleteArticleHandler`.
- Der Upload einer Backup-ZIP beim Import (sowohl im Installer als auch
  über die Admin-Oberfläche) unterliegt den PHP-Grenzwerten
  `upload_max_filesize`/`post_max_size` aus der Server-Konfiguration. Bei
  sehr großen Datenmengen (viele/große Artikelbilder) müssen diese Werte in
  der `php.ini` entsprechend hochgesetzt werden.
