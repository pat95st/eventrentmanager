-- Materialverleih Software — Datenbankschema (MariaDB 10.6+)
-- Zeichensatz/Kollation durchgängig utf8mb4 für Sonderzeichen & Emojis.

--
-- Diese Datei wählt bewusst KEINE Datenbank aus (kein CREATE DATABASE/USE):
-- sie wird in die Datenbank geladen, mit der der Aufrufer verbunden ist —
-- Web-Installer, "mysql <datenbank> < database/schema.sql" (siehe README)
-- und die Acceptance-Suite (tests/acceptance.suite.yml, eigene Test-Datenbank).
-- Ein fest verdrahtetes "USE materialverleih" würde die Test-Suite je nach
-- Rechten des Test-Benutzers abbrechen lassen oder — schlimmer — in die
-- Produktivdatenbank schreiben.

-- ---------------------------------------------------------------------
-- Kategorien (selbstreferenzierender Baum über parent_id)
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    parent_id   CHAR(36)     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL,
    INDEX idx_categories_parent (parent_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Textvorlagen für Schlusstexte (Rechnungen/Angebote/Auftragsbestätigungen),
-- in Ordnern organisiert — Baumstruktur analog categories/Category oben
-- (siehe Migration 094).
-- ---------------------------------------------------------------------
CREATE TABLE closing_text_template_folders (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    parent_id   CHAR(36)     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_closing_text_template_folders_parent
        FOREIGN KEY (parent_id) REFERENCES closing_text_template_folders (id) ON DELETE SET NULL,
    INDEX idx_closing_text_template_folders_parent (parent_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE closing_text_templates (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    folder_id   CHAR(36)     NULL,
    name        VARCHAR(255) NOT NULL,
    content     MEDIUMTEXT   NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_closing_text_templates_folder
        FOREIGN KEY (folder_id) REFERENCES closing_text_template_folders (id) ON DELETE SET NULL,
    INDEX idx_closing_text_templates_folder (folder_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Hersteller
-- ---------------------------------------------------------------------
CREATE TABLE manufacturers (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    prefix      VARCHAR(20)  NULL, -- Präfix für Inventurnummern, z. B. "BOS" (siehe inventory_numbers)
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_manufacturers_name (name),
    UNIQUE KEY uq_manufacturers_prefix (prefix)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Stammdaten-Katalog für abrechenbare Arbeitszeit-Typen (z. B. "Techniker-
-- Stunde", "Meister-Stunde"), analog zu manufacturers — siehe
-- App\Domain\LaborTime\LaborTimeArticle. Der Stundensatz wird beim
-- Hinzufügen zu einem Angebot/einer Rechnung auf die jeweilige Position
-- gesnapshotet (siehe offer_labor_items / invoice_labor_items), spätere
-- Änderungen hier wirken sich also NICHT auf bereits gespeicherte
-- Positionen aus.
CREATE TABLE labor_time_articles (
    id           CHAR(36)      NOT NULL PRIMARY KEY,
    name         VARCHAR(255)  NOT NULL,
    hourly_rate  DECIMAL(10, 2) NOT NULL,
    daily_rate   DECIMAL(10, 2) NULL, -- optionaler Tagessatz, siehe Migration 101
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_labor_time_articles_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Artikel
-- ---------------------------------------------------------------------
CREATE TABLE articles (
    id                  CHAR(36)        NOT NULL PRIMARY KEY,
    name                VARCHAR(255)    NOT NULL,
    manufacturer_id     CHAR(36)        NULL, -- NULL bei Bundles (Migration 111)
    prefix              VARCHAR(20)     NULL, -- Präfix für Inventurnummern, z. B. "AKS" (siehe inventory_numbers)
    category_id         CHAR(36)        NULL,
    description         TEXT            NULL,
    document_short_text VARCHAR(500)    NULL, -- Kurztext für Dokumente, kursiv unter dem Namen (Migration 109)
    document_image_id   CHAR(36)        NULL, -- Galeriebild (article_images.id) für Dokumente, ohne FK (Migration 109)
    weight_value        DECIMAL(10, 3)  NULL,
    weight_unit         ENUM('g','kg')  NULL,
    dimensions          VARCHAR(255)    NULL,
    is_19_inch          TINYINT(1)      NOT NULL DEFAULT 0, -- 19-Zoll-Gerät (Rack-montierbar)?
    height_units        SMALLINT UNSIGNED NULL, -- Höheneinheiten (HE), nur relevant, wenn is_19_inch = 1
    install_depth_cm    DECIMAL(6, 1)   NULL, -- Einbautiefe in cm, nur relevant, wenn is_19_inch = 1
    purchase_price      DECIMAL(10, 2)  NULL, -- Einkaufspreis in Euro; optional
    requires_electrical_inspection TINYINT(1) NOT NULL DEFAULT 0, -- Elektroprüfung (z. B. DGUV V3) nötig?
    protection_class    ENUM('I','II','III') NULL, -- Schutzklasse, nur relevant/gepflegt, wenn requires_electrical_inspection = 1, siehe Migration 096
    has_firmware        TINYINT(1)      NOT NULL DEFAULT 0, -- Gerät besitzt eine aktualisierbare Firmware? siehe firmware_versions
    is_billable         TINYINT(1)      NOT NULL DEFAULT 1, -- bei einer Vermietung als eigener Posten abgerechnet (sonst Zubehör, z. B. Kabel)
    is_rentable_individually TINYINT(1)  NOT NULL DEFAULT 1, -- 0 = nur als Bestandteil eines Bundles vermietbar (Migration 107)
    is_bundle           TINYINT(1)      NOT NULL DEFAULT 0, -- Artikel ist ein Bundle aus anderen Artikeln (Migration 107)
    is_active           TINYINT(1)      NOT NULL DEFAULT 1, -- deaktivierte Artikel bleiben in der Artikelverwaltung sichtbar, verschwinden aber aus der Artikelauswahl (siehe 054_add_article_active_flag.sql)
    daily_rental_price  DECIMAL(10, 2)  NULL, -- Preis pro Einsatztag in Euro; nur relevant, wenn is_billable = 1
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_articles_manufacturer
        FOREIGN KEY (manufacturer_id) REFERENCES manufacturers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_articles_category
        FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    INDEX idx_articles_manufacturer (manufacturer_id),
    INDEX idx_articles_category (category_id),
    UNIQUE KEY uq_articles_manufacturer_prefix (manufacturer_id, prefix),
    FULLTEXT INDEX ftx_articles_search (name, description)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Bestandteile eines Bundle-Artikels (Migration 107).
CREATE TABLE article_bundle_components (
    id                   CHAR(36)     NOT NULL PRIMARY KEY,
    bundle_article_id    CHAR(36)     NOT NULL,
    component_article_id CHAR(36)     NOT NULL,
    quantity             INT UNSIGNED NOT NULL,
    sort_order           INT          NOT NULL DEFAULT 0,
    CONSTRAINT fk_article_bundle_components_bundle
        FOREIGN KEY (bundle_article_id) REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_bundle_components_component
        FOREIGN KEY (component_article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_article_bundle_components (bundle_article_id, component_article_id),
    INDEX idx_article_bundle_components_component (component_article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Optionale Alternativartikel je Bundle-Bestandteil (Migration 111).
CREATE TABLE article_bundle_component_alternatives (
    id           CHAR(36) NOT NULL PRIMARY KEY,
    component_id CHAR(36) NOT NULL,
    article_id   CHAR(36) NOT NULL,
    sort_order   INT      NOT NULL DEFAULT 0,
    CONSTRAINT fk_bundle_alternatives_component
        FOREIGN KEY (component_id) REFERENCES article_bundle_components (id) ON DELETE CASCADE,
    CONSTRAINT fk_bundle_alternatives_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_bundle_alternatives (component_id, article_id),
    INDEX idx_bundle_alternatives_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Artikelbilder (auf Platte gespeichert, hier nur die Metadaten)
-- ---------------------------------------------------------------------
CREATE TABLE article_images (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    article_id  CHAR(36)     NOT NULL,
    file_path   VARCHAR(500) NOT NULL,
    position    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_images_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_article_images_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Zubehör zu einem Artikel ohne eigene Inventurnummer (nur Anzahl +
-- Bezeichnung). Wird bei der Ausgabe als Vollständigkeitsprüfung abgefragt
-- (siehe rental_items.accessory_checked und ConfirmRentalItemAccessoriesHandler).
-- ---------------------------------------------------------------------
CREATE TABLE article_accessories (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    article_id   CHAR(36)     NOT NULL,
    quantity     INT UNSIGNED NOT NULL,
    description  VARCHAR(255) NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_accessories_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_article_accessories_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lieferanten (Kauf/Zumietung); können Inventurnummern zugeordnet werden
-- (siehe inventory_numbers.supplier_id weiter unten).
-- ---------------------------------------------------------------------
CREATE TABLE suppliers (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    type            ENUM('kauf', 'zumietung') NOT NULL,
    street          VARCHAR(255) NOT NULL,  -- Straße/Hausnummer
    postal_code     VARCHAR(20)  NOT NULL,
    city            VARCHAR(150) NOT NULL,
    phone           VARCHAR(50)  NULL,
    email           VARCHAR(255) NULL,
    customer_number VARCHAR(100) NULL, -- unsere Kundennummer bei diesem Lieferanten; optional
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suppliers_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Belege (eingehende Lieferantenrechnungen/-quittungen), siehe
-- database/migrations/035_add_receipts.sql für die ausführliche Begründung
-- der einzelnen Spalten. Bewusst hier (vor inventory_numbers) angelegt,
-- damit inventory_numbers.receipt_id unten inline per Fremdschlüssel darauf
-- verweisen kann (Erzeugungsreihenfolge).
-- ---------------------------------------------------------------------
CREATE TABLE receipts (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    supplier_id         CHAR(36)      NOT NULL,
    receipt_number      VARCHAR(64)   NULL,
    amount              DECIMAL(10, 2) NOT NULL,
    receipt_date        DATE          NOT NULL,
    category            VARCHAR(20)   NOT NULL DEFAULT 'sonstiges',
    status              VARCHAR(20)   NOT NULL DEFAULT 'offen',
    paid_at             DATETIME      NULL,
    note                TEXT          NULL,
    original_filename   VARCHAR(255)  NOT NULL,
    stored_filename      VARCHAR(255) NOT NULL,
    mime_type           VARCHAR(100)  NOT NULL,
    size_bytes           INT UNSIGNED NOT NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_receipts_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
    INDEX idx_receipts_supplier (supplier_id),
    INDEX idx_receipts_status (status),
    INDEX idx_receipts_date (receipt_date),
    INDEX idx_receipts_category (category)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Beleg-Posteingang: eingehende Belege werden automatisch per Cronjob aus
-- einem E-Mail-Postfach eingesammelt (siehe
-- bin/import-receipt-emails.php, App\Application\ReceiptInbox\Command\
-- ImportReceiptEmailsHandler, App\Infrastructure\Mail\ImapClient/
-- Pop3Client/MimeMessageParser). Singleton-Verbindungseinstellungen
-- (mailbox_settings, analog zu smtp_settings — Passwort ebenfalls im
-- Klartext, siehe dortige Begründung), eine admin-gepflegte
-- Absender-Allow-Liste sowie die eigentlich eingesammelten Nachrichten und
-- ihre Anhänge.
-- ---------------------------------------------------------------------
CREATE TABLE mailbox_settings (
    id            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    protocol      ENUM('imap', 'pop3') NOT NULL DEFAULT 'imap',
    host          VARCHAR(255) NULL,
    port          SMALLINT UNSIGNED NULL,
    encryption    ENUM('none', 'ssl', 'starttls') NOT NULL DEFAULT 'ssl',
    username      VARCHAR(255) NULL,
    password      VARCHAR(255) NULL,
    imap_folder   VARCHAR(255) NOT NULL DEFAULT 'INBOX',
    enabled       TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_mailbox_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO mailbox_settings (id, protocol, host, port, encryption, username, password, imap_folder, enabled)
VALUES (1, 'imap', NULL, NULL, 'ssl', NULL, NULL, 'INBOX', 0);

CREATE TABLE receipt_inbox_allowed_senders (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    email       VARCHAR(255) NOT NULL,
    note        VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_receipt_inbox_allowed_senders_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE receipt_inbox_messages (
    id            CHAR(36)     NOT NULL PRIMARY KEY,
    message_id    VARCHAR(998) NOT NULL,
    from_address  VARCHAR(255) NOT NULL,
    subject       VARCHAR(998) NULL,
    received_at   DATETIME     NULL,
    imported_at   DATETIME     NOT NULL,
    UNIQUE KEY uq_receipt_inbox_messages_message_id (message_id(255))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE receipt_inbox_attachments (
    id                    CHAR(36)     NOT NULL PRIMARY KEY,
    message_id            CHAR(36)     NOT NULL,
    original_filename     VARCHAR(255) NOT NULL,
    stored_filename       VARCHAR(255) NOT NULL,
    mime_type             VARCHAR(100) NOT NULL,
    size_bytes            INT UNSIGNED NOT NULL,
    converted_receipt_id  CHAR(36)     NULL,
    created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receipt_inbox_attachments_message
        FOREIGN KEY (message_id) REFERENCES receipt_inbox_messages (id) ON DELETE CASCADE,
    CONSTRAINT fk_receipt_inbox_attachments_receipt
        FOREIGN KEY (converted_receipt_id) REFERENCES receipts (id) ON DELETE SET NULL,
    INDEX idx_receipt_inbox_attachments_message (message_id),
    INDEX idx_receipt_inbox_attachments_converted (converted_receipt_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Veranstaltungsorte (Stammdaten, wiederverwendbar über mehrere
-- Vermietungen hinweg). latitude/longitude werden beim Anlegen/Ändern
-- serverseitig per OpenStreetMap-Nominatim aus der Adresse geokodiert
-- (siehe App\Infrastructure\Geocoding\NominatimGeocoder) und bleiben NULL,
-- wenn die Geokodierung fehlschlägt oder nicht erreichbar ist — die
-- Kartenanzeige wird dann einfach ausgeblendet.
-- ---------------------------------------------------------------------
CREATE TABLE venues (
    id              CHAR(36)       NOT NULL PRIMARY KEY,
    name            VARCHAR(255)   NOT NULL,
    street          VARCHAR(255)   NOT NULL,
    postal_code     VARCHAR(20)    NOT NULL,
    city            VARCHAR(150)   NOT NULL,
    country         VARCHAR(100)   NULL,
    notes           TEXT           NULL,
    caretaker_name  VARCHAR(150)   NULL, -- Name des Hausmeisters vor Ort, optional
    caretaker_phone VARCHAR(50)    NULL, -- Telefonnummer des Hausmeisters, optional
    latitude        DECIMAL(10, 7) NULL,
    longitude       DECIMAL(10, 7) NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_venues_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Stromanschlüsse eines Veranstaltungsorts (ein Veranstaltungsort kann
-- mehrere haben). Die elektrotechnischen Warnungen (Leitungsschutzschalter
-- größer als vom Anschlusstyp zulässig; eigener Verteiler mit RCD nötig)
-- werden NICHT gespeichert, sondern bei jeder Anzeige live aus
-- connection_type/breaker_rating/rcd_trip_current neu berechnet, siehe
-- App\Domain\Venue\PowerConnection.
-- ---------------------------------------------------------------------
CREATE TABLE venue_power_connections (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    venue_id          CHAR(36)     NOT NULL,
    connection_type   VARCHAR(50)  NOT NULL,
    breaker_rating    VARCHAR(20)  NOT NULL,
    rcd_trip_current  VARCHAR(30)  NOT NULL,
    location          VARCHAR(255) NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_venue_power_connections_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE CASCADE,
    INDEX idx_venue_power_connections_venue (venue_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lagerorte der Firma (z. B. Hauptlager, Außenlager). Ausgabe und
-- Rücklieferung einer Vermietung können jeweils einem Lagerort und einem
-- eigenen Termin zugeordnet werden, siehe rentals.pickup_warehouse_id/
-- return_warehouse_id unten.
-- ---------------------------------------------------------------------
CREATE TABLE warehouses (
    id              CHAR(36)       NOT NULL PRIMARY KEY,
    name            VARCHAR(255)   NOT NULL,
    street          VARCHAR(255)   NOT NULL,
    postal_code     VARCHAR(20)    NOT NULL,
    city            VARCHAR(150)   NOT NULL,
    country         VARCHAR(100)   NULL,
    notes           TEXT           NULL,
    latitude        DECIMAL(10, 7) NULL,
    longitude       DECIMAL(10, 7) NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_warehouses_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Inventurnummern (mehrere pro Artikel, je mit Anschaffungszeitpunkt)
-- "number" wird von der Anwendung automatisch aus den Präfixen von
-- Hersteller und Artikel plus einer laufenden Nummer gebildet, z. B.
-- "BOS-AKS-0001" (siehe App\Domain\InventoryNumber\InventoryNumberGenerator).
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- Im Katalog eines Artikels hinterlegte Firmware-Versionen (nur relevant,
-- wenn articles.has_firmware = 1). Welche Version auf einer konkreten
-- Inventurnummer installiert ist, wird über inventory_numbers.
-- installed_firmware_version_id referenziert (siehe unten).
-- ---------------------------------------------------------------------
CREATE TABLE firmware_versions (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    article_id  CHAR(36)     NOT NULL,
    version     VARCHAR(100) NOT NULL, -- Versionsbezeichnung, z. B. "2.3.1"; Freitext, da Hersteller-Schemata variieren
    released_at DATE         NULL,     -- Erscheinungs-/Anlagedatum laut Hersteller; optional, da oft unbekannt
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_firmware_versions_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_firmware_versions_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE inventory_numbers (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    article_id          CHAR(36)     NOT NULL,
    number              VARCHAR(100) NOT NULL,
    acquisition_date    DATE         NULL,
    supplier_id         CHAR(36)     NULL,  -- optional: Lieferant, von dem dieses Exemplar bezogen wurde
    -- optional: Beleg, über den dieses Exemplar bezogen wurde. Ist diese Spalte
    -- gesetzt, bleibt supplier_id oben bewusst NULL — der Lieferant wird dann
    -- ausschließlich über receipts.supplier_id aufgelöst (siehe
    -- database/migrations/036_add_inventory_number_receipt.sql).
    receipt_id          CHAR(36)     NULL,
    purchase_price      DECIMAL(10, 2) NULL, -- eigener Einkaufspreis, falls abweichend vom Artikel; optional
    storage_location    VARCHAR(255) NULL, -- Freitext-Lagerort dieses Exemplars, z. B. "Regal 3, Fach B"; optional
    installed_firmware_version_id CHAR(36) NULL, -- installierte Firmware-Version (FK auf firmware_versions); bewusst eine Auswahl aus dem Artikel-Katalog, kein Freitext (siehe database/migrations/076_add_firmware.sql)
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_numbers_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_numbers_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_numbers_receipt
        FOREIGN KEY (receipt_id) REFERENCES receipts (id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_numbers_installed_firmware_version
        FOREIGN KEY (installed_firmware_version_id) REFERENCES firmware_versions (id) ON DELETE SET NULL,
    UNIQUE KEY uq_inventory_numbers_number (number),
    INDEX idx_inventory_numbers_article (article_id),
    INDEX idx_inventory_numbers_supplier (supplier_id),
    INDEX idx_inventory_numbers_receipt (receipt_id),
    INDEX idx_inventory_numbers_installed_firmware_version (installed_firmware_version_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- RMA / Wartungsvorgänge je Inventurnummer
-- Der Status wird bewusst NICHT redundant gespeichert, sondern aus
-- sent_at/returned_at abgeleitet (siehe Domain\Rma\RmaStatus). kind
-- unterscheidet die externe Einsendung (Werkstatt/Hersteller) von der
-- Eigenreparatur (siehe rma_receipts für deren verknüpfte Belege).
-- ---------------------------------------------------------------------
CREATE TABLE rmas (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    inventory_number_id     CHAR(36)     NOT NULL,
    kind                    ENUM('extern', 'eigenreparatur') NOT NULL DEFAULT 'extern',
    reason                  VARCHAR(500) NULL,
    service_provider        VARCHAR(255) NULL,
    rma_reference            VARCHAR(100) NULL,
    sent_at                 DATE         NULL,
    returned_at              DATE         NULL,
    notes                   TEXT         NULL,
    worked_hours            DECIMAL(6, 2) NULL,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rmas_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE CASCADE,
    INDEX idx_rmas_inventory_number (inventory_number_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Dokumente zu einem RMA-Vorgang (Rechnungen, E-Mail-PDFs, Lieferscheine, …)
-- Dateien liegen unter storage/uploads/rma/<rma_id>/, hier nur Metadaten.
-- ---------------------------------------------------------------------
CREATE TABLE rma_documents (
    id                  CHAR(36)        NOT NULL PRIMARY KEY,
    rma_id              CHAR(36)        NOT NULL,
    original_filename   VARCHAR(255)    NOT NULL,
    stored_filename     VARCHAR(255)    NOT NULL,
    mime_type           VARCHAR(150)    NOT NULL,
    size_bytes          INT UNSIGNED    NOT NULL,
    uploaded_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rma_documents_rma
        FOREIGN KEY (rma_id) REFERENCES rmas (id) ON DELETE CASCADE,
    INDEX idx_rma_documents_rma (rma_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Fotos des Pakets/der Verpackung VOR dem Versand einer externen RMA
-- (Beweissicherung, siehe App\Domain\Rma\RmaPackagePhoto). Analog zu
-- rma_documents, aber bewusst als eigene Tabelle: nur Bilder erlaubt und
-- eigener Anzeigebereich ("Fotos vor dem Versand") in der Oberfläche.
-- ---------------------------------------------------------------------
CREATE TABLE rma_package_photos (
    id                  CHAR(36)        NOT NULL PRIMARY KEY,
    rma_id              CHAR(36)        NOT NULL,
    original_filename   VARCHAR(255)    NOT NULL,
    stored_filename     VARCHAR(255)    NOT NULL,
    mime_type           VARCHAR(150)    NOT NULL,
    size_bytes          INT UNSIGNED    NOT NULL,
    uploaded_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rma_package_photos_rma
        FOREIGN KEY (rma_id) REFERENCES rmas (id) ON DELETE CASCADE,
    INDEX idx_rma_package_photos_rma (rma_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Übermittlung einzelner Artikel an externe Vertriebskanäle (Google
-- Merchant Center, Google Unternehmensprofil, WhatsApp Business), siehe
-- App\Domain\Article\ArticleChannelListing.
CREATE TABLE article_channel_listings (
    id              CHAR(36)        NOT NULL PRIMARY KEY,
    article_id      CHAR(36)        NOT NULL,
    channel         VARCHAR(30)     NOT NULL,
    enabled         TINYINT(1)      NOT NULL DEFAULT 0,
    external_id     VARCHAR(255)    NULL,
    external_link   VARCHAR(500)    NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'pending',
    last_synced_at  DATETIME        NULL,
    last_error      TEXT            NULL,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_channel_listings_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT uniq_article_channel_listings_article_channel UNIQUE (article_id, channel)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Verknüpfung von Belegen (Ersatzteil-Einkäufen) zu einem RMA-Vorgang vom
-- Typ "eigenreparatur". n:m, da ein Beleg theoretisch mehrere Ersatzteile
-- für dieselbe Reparatur enthalten kann und eine Reparatur mehrere Belege
-- hat.
-- ---------------------------------------------------------------------
CREATE TABLE rma_receipts (
    id          CHAR(36)  NOT NULL PRIMARY KEY,
    rma_id      CHAR(36)  NOT NULL,
    receipt_id  CHAR(36)  NOT NULL,
    created_at  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rma_receipts_rma
        FOREIGN KEY (rma_id) REFERENCES rmas (id) ON DELETE CASCADE,
    CONSTRAINT fk_rma_receipts_receipt
        FOREIGN KEY (receipt_id) REFERENCES receipts (id) ON DELETE CASCADE,
    UNIQUE KEY uq_rma_receipts_rma_receipt (rma_id, receipt_id),
    INDEX idx_rma_receipts_rma (rma_id),
    INDEX idx_rma_receipts_receipt (receipt_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Elektroprüfungen (z. B. DGUV V3) je Inventurnummer. Nur relevant für
-- Artikel mit requires_electrical_inspection = 1 (siehe articles), aber
-- technisch nicht erzwungen, damit ein nachträglich geändertes Flag keine
-- bereits erfassten Prüfungen verwaist.
-- ---------------------------------------------------------------------
CREATE TABLE electrical_inspections (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    inventory_number_id     CHAR(36)     NOT NULL,
    inspected_at            DATE         NOT NULL,
    validity_years          TINYINT      NOT NULL DEFAULT 1,
    passed                  TINYINT(1)   NOT NULL DEFAULT 1,
    protective_conductor_resistance_ohm DECIMAL(5, 3) NULL, -- Schutzleiterwiderstand in Ω, nur bei Schutzklasse I sinnvoll, siehe Migration 096
    insulation_resistance_mohm          DECIMAL(6, 2) NULL, -- Isolationswiderstand in MΩ
    protective_conductor_current_ma     DECIMAL(6, 2) NULL, -- Schutzleiterstrom/Ableitstrom in mA, nur bei Schutzklasse I sinnvoll
    touch_current_ma                    DECIMAL(6, 2) NULL, -- Berührungsstrom in mA
    inspector               VARCHAR(255) NULL,
    notes                   TEXT         NULL,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_electrical_inspections_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE CASCADE,
    INDEX idx_electrical_inspections_inventory_number (inventory_number_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Kunden (Kundenverwaltung)
-- ---------------------------------------------------------------------
CREATE TABLE customers (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    customer_number VARCHAR(50)  NOT NULL, -- fortlaufend, siehe Migration 095
    type            ENUM('privat', 'geschaeftlich') NOT NULL,
    company_name    VARCHAR(255) NULL,      -- nur bei type = 'geschaeftlich' befüllt
    first_name      VARCHAR(150) NOT NULL,
    last_name       VARCHAR(150) NOT NULL,
    street          VARCHAR(255) NOT NULL,  -- Straße/Hausnummer
    address_line2   VARCHAR(255) NULL,      -- Adresszusatz
    postal_code     VARCHAR(20)  NOT NULL,
    city            VARCHAR(150) NOT NULL,
    phone           VARCHAR(50)  NULL,
    email           VARCHAR(255) NULL,
    telegram_chat_id VARCHAR(64) NULL, -- siehe App\Domain\Messaging, Migration 077
    id_checked_at   DATETIME     NULL, -- einmalig je Kunde gesetzt, siehe Migration 082
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customers_last_name (last_name),
    INDEX idx_customers_company_name (company_name),
    CONSTRAINT uq_customers_customer_number UNIQUE (customer_number)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Siehe Migration 100: mehrere Ansprechpartner je Kunde.
CREATE TABLE customer_contacts (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    customer_id CHAR(36)     NOT NULL,
    name        VARCHAR(255) NOT NULL,
    job_title   VARCHAR(150) NULL,
    phone       VARCHAR(50)  NULL,
    email       VARCHAR(255) NULL,
    street        VARCHAR(255) NULL,
    address_line2  VARCHAR(255) NULL,
    postal_code    VARCHAR(20)  NULL,
    city           VARCHAR(255) NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    CONSTRAINT fk_customer_contacts_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    INDEX idx_customer_contacts_customer (customer_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cases (Flightcase-Modelle, analog zu Artikeln: eigener Hersteller aus
-- derselben Herstellerliste, eigenes Präfix für Inventurnummern der
-- einzelnen physischen Case-Exemplare, siehe case_inventory_numbers)
-- ---------------------------------------------------------------------
CREATE TABLE case_items (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    name                VARCHAR(255) NOT NULL,
    manufacturer_id     CHAR(36)     NOT NULL,
    prefix              VARCHAR(20)  NULL, -- Präfix für Inventurnummern, z. B. "RCK"
    description         TEXT         NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_case_items_manufacturer
        FOREIGN KEY (manufacturer_id) REFERENCES manufacturers (id) ON DELETE RESTRICT,
    INDEX idx_case_items_manufacturer (manufacturer_id),
    UNIQUE KEY uq_case_items_manufacturer_prefix (manufacturer_id, prefix),
    FULLTEXT INDEX ftx_case_items_search (name, description)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Case-Exemplare (physische Instanzen eines Case-Modells, je mit eigener
-- Inventurnummer, analog zu inventory_numbers bei Artikeln).
-- ---------------------------------------------------------------------
CREATE TABLE case_inventory_numbers (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    case_id             CHAR(36)     NOT NULL,
    number              VARCHAR(100) NOT NULL,
    acquisition_date    DATE         NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_case_inventory_numbers_case
        FOREIGN KEY (case_id) REFERENCES case_items (id) ON DELETE CASCADE,
    UNIQUE KEY uq_case_inventory_numbers_number (number),
    INDEX idx_case_inventory_numbers_case (case_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Inhalt eines Case-Exemplars: welche Artikel (auf Artikel-Ebene, nicht
-- gebunden an eine konkrete Artikel-Inventurnummer) mit welcher Menge in
-- diesem Case-Exemplar stecken. article_id bleibt bewusst RESTRICT (kein
-- CASCADE), damit ein Artikel nicht gelöscht werden kann, solange er noch
-- in einem Case gepackt ist (siehe DeleteArticleHandler).
-- ---------------------------------------------------------------------
CREATE TABLE case_content_items (
    id                          CHAR(36)     NOT NULL PRIMARY KEY,
    case_inventory_number_id    CHAR(36)     NOT NULL,
    article_id                  CHAR(36)     NOT NULL,
    quantity                    INT UNSIGNED NOT NULL,
    created_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_case_content_items_case_inventory_number
        FOREIGN KEY (case_inventory_number_id) REFERENCES case_inventory_numbers (id) ON DELETE CASCADE,
    CONSTRAINT fk_case_content_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_case_content_items_case_article (case_inventory_number_id, article_id),
    INDEX idx_case_content_items_case_inventory_number (case_inventory_number_id),
    INDEX idx_case_content_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Vermietungen an Endkunden. Artikel werden zunächst nur auf Artikel-Ebene
-- (mit Menge) geplant, siehe rental_items; die tatsächliche Ausgabe anhand
-- der Inventurnummer erfolgt separat über rental_issued_items.
-- ---------------------------------------------------------------------
CREATE TABLE rentals (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    customer_id       CHAR(36)     NOT NULL,
    event_name        VARCHAR(255) NOT NULL,
    event_location    VARCHAR(255) NULL,
    venue_id          CHAR(36)     NULL, -- optionaler Verweis auf einen Stammdaten-Veranstaltungsort (siehe venues)
    -- Transport-/Logistik-Zuordnung: Ausgabe und Rücklieferung können jeweils
    -- einem Lagerort und einem eigenen Termin zugeordnet werden. Sind
    -- pickup_warehouse_id und return_warehouse_id identisch (oder ist nur
    -- eines der beiden Felder gesetzt), handelt es sich fachlich um "ein
    -- Lager für Ausgabe und Rücklieferung" — der Transport davor/danach wird
    -- dann in transport_note beschrieben. Unterscheiden sie sich, handelt es
    -- sich um getrennte Termine an unterschiedlichen Lagern. Alle Felder
    -- bleiben optional (siehe App\Domain\Rental\Rental).
    pickup_warehouse_id CHAR(36)   NULL,
    pickup_at           DATETIME   NULL,
    return_warehouse_id CHAR(36)   NULL,
    return_at           DATETIME   NULL,
    transport_note       TEXT      NULL,
    start_date        DATE         NOT NULL,
    end_date          DATE         NOT NULL,
    deployment_days   INT UNSIGNED NOT NULL,
    terms_signed_at      DATETIME     NULL, -- siehe App\Domain\Rental\Rental::$termsSignedAt, Migration 082
    terms_signature_file VARCHAR(255) NULL, -- nur Dateiname in storage/uploads/rental-terms-signatures/
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rentals_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_rentals_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    CONSTRAINT fk_rentals_pickup_warehouse
        FOREIGN KEY (pickup_warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    CONSTRAINT fk_rentals_return_warehouse
        FOREIGN KEY (return_warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    INDEX idx_rentals_customer (customer_id),
    INDEX idx_rentals_venue (venue_id),
    INDEX idx_rentals_pickup_warehouse (pickup_warehouse_id),
    INDEX idx_rentals_return_warehouse (return_warehouse_id),
    INDEX idx_rentals_dates (start_date, end_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Geplante Artikel je Vermietung (Artikel-Ebene mit Menge, keine konkrete
-- Inventurnummer). article_id bleibt RESTRICT, damit ein Artikel nicht
-- gelöscht werden kann, solange er noch in einer Vermietung geplant ist
-- (siehe DeleteArticleHandler).
-- ---------------------------------------------------------------------
CREATE TABLE rental_items (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id           CHAR(36)     NOT NULL,
    article_id          CHAR(36)     NOT NULL,
    quantity            INT UNSIGNED NOT NULL,
    -- Vollständigkeitsprüfung des Zubehörs bei der Ausgabe (Modal); ohne
    -- Bedeutung, wenn der Artikel kein Zubehör hat (siehe article_accessories).
    accessory_checked   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_items_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_items_rental_article (rental_id, article_id),
    INDEX idx_rental_items_rental (rental_id),
    INDEX idx_rental_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- In einer Vermietung gebuchte Bundles (Migration 107); die physischen
-- Bestandteile stehen zusätzlich als rental_items.
CREATE TABLE rental_bundles (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id         CHAR(36)     NOT NULL,
    bundle_article_id CHAR(36)     NOT NULL,
    quantity          INT UNSIGNED NOT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_bundles_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_bundles_article
        FOREIGN KEY (bundle_article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_bundles (rental_id, bundle_article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Tatsächlich belegte Artikel je gebuchtem Bundle (Migration 111), z. B.
-- eine Alternative statt des Hauptartikels.
CREATE TABLE rental_bundle_allocations (
    id               CHAR(36)     NOT NULL PRIMARY KEY,
    rental_bundle_id CHAR(36)     NOT NULL,
    article_id       CHAR(36)     NOT NULL,
    quantity         INT UNSIGNED NOT NULL,
    CONSTRAINT fk_rental_bundle_allocations_bundle
        FOREIGN KEY (rental_bundle_id) REFERENCES rental_bundles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_bundle_allocations_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_bundle_allocations (rental_bundle_id, article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tatsächlich ausgegebenes Material je Vermietung, anhand der konkreten
-- Inventurnummer (Ausgabe-Checkliste). inventory_number_id bleibt RESTRICT,
-- damit eine Inventurnummer nicht gelöscht werden kann, solange sie noch
-- als ausgegeben erfasst ist.
-- ---------------------------------------------------------------------
CREATE TABLE rental_issued_items (
    id                      CHAR(36)   NOT NULL PRIMARY KEY,
    rental_id               CHAR(36)   NOT NULL,
    article_id              CHAR(36)   NOT NULL,
    inventory_number_id     CHAR(36)   NOT NULL,
    issued_at               DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Rückgabe-Checkliste: wann zurückgenommen (NULL = noch nicht
    -- zurückgegeben) und ob der Funktionstest erfolgreich war (NULL = noch
    -- kein Funktionstest, solange returned_at NULL ist). Bei fehlgeschlagenem
    -- Test wird automatisch ein RMA-Vorgang angelegt (ReturnRentalItemHandler).
    returned_at             DATETIME   NULL,
    function_test_passed    TINYINT(1) NULL,
    CONSTRAINT fk_rental_issued_items_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_issued_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_rental_issued_items_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE RESTRICT,
    -- Keine globale UNIQUE-Sperre auf inventory_number_id: dieselbe
    -- Inventurnummer darf mehreren Vermietungen zugeordnet sein, solange
    -- sich deren Zeiträume nicht überschneiden (siehe
    -- IssueRentalItemHandler / isInventoryNumberIssuedDuringRange).
    INDEX idx_rental_issued_items_rental (rental_id),
    INDEX idx_rental_issued_items_article (article_id),
    INDEX idx_rental_issued_items_inventory_number (inventory_number_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Minutenprogramme (Ablaufpläne je Veranstaltung), siehe Migration 081.
-- ---------------------------------------------------------------------
CREATE TABLE minute_programs (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id               CHAR(36)     NOT NULL,
    name                    VARCHAR(255) NOT NULL,
    room                    VARCHAR(255) NULL,
    start_at                DATETIME     NOT NULL,
    jingle_before           TINYINT(1)   NOT NULL DEFAULT 0,
    handheld_mic_count      INT UNSIGNED NOT NULL DEFAULT 0,
    headset_mic_count       INT UNSIGNED NOT NULL DEFAULT 0,
    current_delay_minutes   INT          NOT NULL DEFAULT 0,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_minute_programs_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    INDEX idx_minute_programs_rental (rental_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE minute_program_items (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    minute_program_id   CHAR(36)     NOT NULL,
    sort_order          INT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL,
    description         TEXT         NULL,
    note_general        TEXT         NULL,
    note_sound          TEXT         NULL,
    note_light          TEXT         NULL,
    note_video          TEXT         NULL,
    note_stage          TEXT         NULL,
    duration_minutes    INT UNSIGNED NULL,
    CONSTRAINT fk_minute_program_items_program
        FOREIGN KEY (minute_program_id) REFERENCES minute_programs (id) ON DELETE CASCADE,
    INDEX idx_minute_program_items_program (minute_program_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE minute_program_templates (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    name                    VARCHAR(255) NOT NULL,
    jingle_before           TINYINT(1)   NOT NULL DEFAULT 0,
    handheld_mic_count      INT UNSIGNED NOT NULL DEFAULT 0,
    headset_mic_count       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE minute_program_template_items (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    template_id     CHAR(36)     NOT NULL,
    sort_order      INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    description     TEXT         NULL,
    note_general    TEXT         NULL,
    note_sound      TEXT         NULL,
    note_light      TEXT         NULL,
    note_video      TEXT         NULL,
    note_stage      TEXT         NULL,
    duration_minutes INT UNSIGNED NULL,
    CONSTRAINT fk_minute_program_template_items_template
        FOREIGN KEY (template_id) REFERENCES minute_program_templates (id) ON DELETE CASCADE,
    INDEX idx_minute_program_template_items_template (template_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE minute_program_share_links (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    minute_program_id   CHAR(36)     NOT NULL,
    token               VARCHAR(64)  NOT NULL,
    mode                ENUM('read', 'read_write') NOT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at          DATETIME     NULL,
    CONSTRAINT fk_minute_program_share_links_program
        FOREIGN KEY (minute_program_id) REFERENCES minute_programs (id) ON DELETE CASCADE,
    UNIQUE KEY uq_minute_program_share_links_token (token),
    INDEX idx_minute_program_share_links_program (minute_program_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Hinweisdokumente (siehe database/migrations/083_add_notice_documents.sql)
-- ---------------------------------------------------------------------

CREATE TABLE notice_documents (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    name                VARCHAR(255) NOT NULL,
    original_filename   VARCHAR(255) NOT NULL,
    stored_filename     VARCHAR(255) NOT NULL,
    mime_type           VARCHAR(127) NOT NULL,
    size_bytes          INT UNSIGNED NOT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE notice_document_articles (
    notice_document_id CHAR(36) NOT NULL,
    article_id          CHAR(36) NOT NULL,
    PRIMARY KEY (notice_document_id, article_id),
    CONSTRAINT fk_notice_document_articles_document
        FOREIGN KEY (notice_document_id) REFERENCES notice_documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_notice_document_articles_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_notice_document_articles_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Benutzerverwaltung & Authentifizierung (siehe database/migrations/
-- 008_add_users_and_auth.sql für Details/Kommentare und den Seed des
-- Standard-Administrators).
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                    CHAR(36)     NOT NULL PRIMARY KEY,
    email                 VARCHAR(255) NOT NULL,
    first_name            VARCHAR(100) NULL,
    last_name             VARCHAR(100) NULL,
    -- NULL = kein Passwort gesetzt (Benutzer wurde per E-Mail-Einladung
    -- angelegt und muss es zunächst über einen Setup-Link festlegen, siehe
    -- App\Application\Auth\PasswordSetupTokenIssuer). password_verify()
    -- gegen NULL wird von AuthenticatePasswordHandler abgefangen.
    password_hash         VARCHAR(255) NULL,
    permissions           VARCHAR(255) NOT NULL DEFAULT '',
    two_factor_method     ENUM('none', 'totp', 'email') NOT NULL DEFAULT 'none',
    totp_secret            VARCHAR(64)  NULL,
    must_change_password   TINYINT(1)   NOT NULL DEFAULT 0,
    -- Erzwingt die Einrichtung eines zweiten Faktors beim ersten Login
    -- (analog must_change_password), siehe App\Infrastructure\Http\Auth\AuthGuard.
    must_setup_two_factor  TINYINT(1)   NOT NULL DEFAULT 0,
    is_active               TINYINT(1)   NOT NULL DEFAULT 1,
    -- Standard-Seitengröße für paginierte Listen (Artikel, Veranstaltungen,
    -- Kunden, ...), vom Benutzer selbst unter /profil/darstellung
    -- änderbar; siehe App\Infrastructure\Http\Pagination.
    default_page_size      SMALLINT UNSIGNED NOT NULL DEFAULT 25,
    -- Sprachpräferenz der Oberfläche ('de'/'en'), vom Benutzer selbst unter
    -- /profil/darstellung änderbar; siehe App\Infrastructure\Http\Translator.
    locale                  VARCHAR(5)   NOT NULL DEFAULT 'de',
    -- Ob dieser Benutzer per E-Mail benachrichtigt werden möchte, wenn der
    -- Beleg-Posteingang-Cronjob neue Nachrichten eingesammelt hat (nur
    -- wirksam für Administratoren), vom Benutzer selbst unter
    -- /profil/darstellung änderbar; siehe
    -- App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler.
    notify_on_new_receipt_email TINYINT(1) NOT NULL DEFAULT 0,
    -- Kommaseparierte Liste aktivierter Dashboard-Bereiche (siehe
    -- App\Domain\Dashboard\DashboardWidget), vom Benutzer selbst unter
    -- /profil/dashboard änderbar. NULL = noch nicht angepasst -> es werden
    -- alle Bereiche angezeigt, für die der Benutzer ohnehin ein Recht hat
    -- (siehe DashboardController). Ein (ggf. leerer) String bedeutet
    -- "explizit konfiguriert".
    dashboard_widgets       TEXT         NULL,
    created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE login_challenges (
    id           CHAR(36)   NOT NULL PRIMARY KEY,
    user_id      CHAR(36)   NOT NULL,
    code_hash    VARCHAR(255) NOT NULL,
    expires_at   DATETIME   NOT NULL,
    consumed_at  DATETIME   NULL,
    created_at   DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_challenges_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_login_challenges_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE smtp_settings (
    id            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    host          VARCHAR(255) NOT NULL DEFAULT '',
    port          INT UNSIGNED NOT NULL DEFAULT 587,
    encryption    ENUM('none', 'ssl', 'tls') NOT NULL DEFAULT 'tls',
    username      VARCHAR(255) NULL,
    password      VARCHAR(255) NULL,
    from_address  VARCHAR(255) NOT NULL DEFAULT '',
    from_name     VARCHAR(255) NOT NULL DEFAULT 'Materialverleih',
    reply_to_address    VARCHAR(255) NULL,
    archive_bcc_address VARCHAR(255) NULL,
    archive_categories  TEXT NULL,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_smtp_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO smtp_settings (id, host, port, encryption, username, password, from_address, from_name, reply_to_address, archive_bcc_address, archive_categories)
VALUES (1, '', 587, 'tls', NULL, NULL, '', 'Materialverleih', NULL, NULL, 'login_otp,password_setup_invite,password_reset,offer_pdf,packing_list_pdf,invoice_pdf,test_mail,receipt_inbox_notification');

-- ---------------------------------------------------------------------
-- Firmeneinstellungen (Name/Adresse/Logo), Singleton-Tabelle analog zu
-- smtp_settings.
-- ---------------------------------------------------------------------
CREATE TABLE company_settings (
    id            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    name          VARCHAR(255) NULL,
    brand_name    VARCHAR(255) NULL,
    street        VARCHAR(255) NULL,
    postal_code   VARCHAR(20)  NULL,
    city          VARCHAR(255) NULL,
    country       VARCHAR(255) NULL,
    phone         VARCHAR(64)  NULL, -- für die Dokument-Fußzeile (Migration 108)
    email         VARCHAR(255) NULL,
    website       VARCHAR(255) NULL,
    logo_filename VARCHAR(255) NULL,
    logo_max_height_px SMALLINT NULL,
    dark_logo_filename VARCHAR(255) NULL,
    background_image_filename VARCHAR(255) NULL,
    favicon_filename VARCHAR(255) NULL, -- eigenes Favicon (Migration 112) -- optionales Hintergrundbild für die gesamte Oberfläche, siehe App\Infrastructure\Http\CompanyBranding::backgroundImageUrl()
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_company_settings_singleton CHECK (id = 1),
    CONSTRAINT chk_company_settings_logo_max_height_px
        CHECK (logo_max_height_px IS NULL OR (logo_max_height_px BETWEEN 16 AND 200))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO company_settings (id, name, brand_name, street, postal_code, city, country, logo_filename, logo_max_height_px, dark_logo_filename)
VALUES (1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- Einstellungen für die externen Vertriebskanal-Integrationen (Google
-- Merchant Center, Google Unternehmensprofil, WhatsApp Business), siehe
-- App\Domain\Settings\GoogleMerchantSettings/GoogleBusinessSettings/
-- WhatsappBusinessSettings/IntegrationBaseSettings. Je Tabelle eine
-- Singleton-Zeile analog zu company_settings.
-- ---------------------------------------------------------------------
CREATE TABLE google_merchant_settings (
    id                              TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    merchant_id                     VARCHAR(50)  NULL,
    service_account_json_filename   VARCHAR(255) NULL,
    target_country                  VARCHAR(2)   NOT NULL DEFAULT 'DE',
    content_language                VARCHAR(5)   NOT NULL DEFAULT 'de',
    product_link_template           VARCHAR(500) NULL,
    updated_at                      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_google_merchant_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO google_merchant_settings (id) VALUES (1);

CREATE TABLE google_business_settings (
    id                          TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    client_id                   VARCHAR(255) NULL,
    client_secret               VARCHAR(255) NULL,
    access_token                VARCHAR(2048) NULL,
    refresh_token               VARCHAR(2048) NULL,
    access_token_expires_at     DATETIME     NULL,
    account_id                  VARCHAR(255) NULL,
    location_id                 VARCHAR(255) NULL,
    connected_at                DATETIME     NULL,
    updated_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_google_business_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO google_business_settings (id) VALUES (1);

CREATE TABLE whatsapp_business_settings (
    id            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    access_token  VARCHAR(2048) NULL,
    catalog_id    VARCHAR(255) NULL,
    currency      VARCHAR(3)   NOT NULL DEFAULT 'EUR',
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_whatsapp_business_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO whatsapp_business_settings (id) VALUES (1);

CREATE TABLE integration_base_settings (
    id          TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    base_url    VARCHAR(255) NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_integration_base_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO integration_base_settings (id) VALUES (1);

-- ---------------------------------------------------------------------
-- Optionale, token-gesicherte Lese-REST-API für Artikel, siehe
-- App\Domain\Api\ApiToken/ApiSettings.
-- ---------------------------------------------------------------------
CREATE TABLE api_settings (
    id          TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    enabled     TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_api_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO api_settings (id, enabled) VALUES (1, 0);

CREATE TABLE api_tokens (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    name              VARCHAR(255) NOT NULL,
    token_hash        CHAR(64)     NOT NULL,
    token_last_four   CHAR(4)      NOT NULL,
    ip_allowlist      TEXT         NULL,
    enabled           TINYINT(1)   NOT NULL DEFAULT 1,
    last_used_at      DATETIME     NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uniq_api_tokens_token_hash UNIQUE (token_hash)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CalDAV-Server: Kalender, Zugriffsrechte pro User, CalDAV-App-Passwörter.
-- Siehe App\Domain\Calendar\Calendar/CalendarKind/CalDavCredential sowie
-- Migration 062_add_calendars.sql für ausführliche Kommentare.
-- ---------------------------------------------------------------------
CREATE TABLE calendars (
    id                CHAR(36)                 NOT NULL PRIMARY KEY,
    kind              ENUM('event', 'rental')  NOT NULL,
    name              VARCHAR(255)              NOT NULL,
    enabled           TINYINT(1)                NOT NULL DEFAULT 0,
    category_id       CHAR(36)                  NULL,
    manufacturer_id   CHAR(36)                  NULL,
    warehouse_id      CHAR(36)                  NULL,
    venue_id          CHAR(36)                  NULL,
    created_at        DATETIME                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME                  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_calendars_category
        FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_calendars_manufacturer
        FOREIGN KEY (manufacturer_id) REFERENCES manufacturers (id) ON DELETE SET NULL,
    CONSTRAINT fk_calendars_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    CONSTRAINT fk_calendars_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    INDEX idx_calendars_kind (kind)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO calendars (id, kind, name, enabled) VALUES
    ('00000000-0000-4000-8000-000000000001', 'event', 'Veranstaltungen', 0);

CREATE TABLE calendar_access (
    id           CHAR(36) NOT NULL PRIMARY KEY,
    calendar_id  CHAR(36) NOT NULL,
    user_id      CHAR(36) NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_calendar_access_calendar
        FOREIGN KEY (calendar_id) REFERENCES calendars (id) ON DELETE CASCADE,
    CONSTRAINT fk_calendar_access_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY uq_calendar_access_calendar_user (calendar_id, user_id),
    INDEX idx_calendar_access_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE caldav_credentials (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    user_id           CHAR(36)     NOT NULL,
    name              VARCHAR(255) NOT NULL,
    token_hash        CHAR(64)     NOT NULL,
    token_last_four   CHAR(4)      NOT NULL,
    last_used_at      DATETIME     NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_caldav_credentials_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uniq_caldav_credentials_token_hash UNIQUE (token_hash),
    INDEX idx_caldav_credentials_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Zertifikat zum Signieren heruntergeladener .mobileconfig-Profile, siehe
-- App\Domain\Settings\CalDavSigningSettings sowie Migration
-- 063_add_caldav_signing_settings.sql für ausführliche Kommentare.
CREATE TABLE caldav_signing_settings (
    id                   TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    certificate_filename VARCHAR(255) NULL,
    private_key_filename VARCHAR(255) NULL,
    is_self_signed       TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_caldav_signing_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO caldav_signing_settings (id) VALUES (1);

-- ---------------------------------------------------------------------
-- Rechtstexte (Vermietbedingungen/Impressum/Datenschutzerklärung),
-- Singleton-Tabelle analog zu company_settings. Impressum und
-- Datenschutzerklärung können wahlweise als Markdown-Text ODER als externe
-- URL gepflegt werden (siehe *_mode-Spalten); Vermietbedingungen sind
-- ausschließlich Markdown.
-- ---------------------------------------------------------------------
CREATE TABLE legal_settings (
    id                    TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    rental_terms_markdown TEXT         NULL,
    imprint_mode          VARCHAR(20)  NOT NULL DEFAULT 'markdown',
    imprint_markdown      TEXT         NULL,
    imprint_url           VARCHAR(2000) NULL,
    privacy_mode          VARCHAR(20)  NOT NULL DEFAULT 'markdown',
    privacy_markdown      TEXT         NULL,
    privacy_url           VARCHAR(2000) NULL,
    updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_legal_settings_singleton CHECK (id = 1),
    CONSTRAINT chk_legal_settings_imprint_mode CHECK (imprint_mode IN ('markdown', 'link')),
    CONSTRAINT chk_legal_settings_privacy_mode CHECK (privacy_mode IN ('markdown', 'link'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO legal_settings (id, rental_terms_markdown, imprint_mode, imprint_markdown, imprint_url, privacy_mode, privacy_markdown, privacy_url)
VALUES (1, NULL, 'markdown', NULL, NULL, 'markdown', NULL, NULL);

-- ---------------------------------------------------------------------
-- Admin-bearbeitbarer Einleitungs-/Schlusstext im eigentlichen Angebots-PDF
-- (siehe App\Infrastructure\Pdf\OfferPdfGenerator), Singleton-Tabelle
-- analog zu legal_settings/company_settings. Getrennt von der "Angebot per
-- E-Mail"-Vorlage (email_templates, Schlüssel offer_pdf) — dort geht es um
-- den Text der E-Mail, hier um den Text im PDF-Dokument selbst.
-- ---------------------------------------------------------------------
-- intro_html/closing_html enthalten ein vom Rich-Text-Editor geliefertes,
-- serverseitig bereinigtes HTML-Fragment (siehe App\Infrastructure\RichText\
-- HtmlSanitizer) — bis Migration 064 hieß dies intro_markdown/
-- closing_markdown und enthielt Markdown, siehe dortiger Kommentar.
CREATE TABLE offer_pdf_settings (
    id            TINYINT    NOT NULL PRIMARY KEY DEFAULT 1,
    intro_html    MEDIUMTEXT NULL,
    closing_html  MEDIUMTEXT NULL,
    updated_at    DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_offer_pdf_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO offer_pdf_settings (id, intro_html, closing_html) VALUES (1, NULL, NULL);

-- ---------------------------------------------------------------------
-- Firmenweite Rechnungseinstellungen (siehe App\Domain\Settings\
-- InvoiceSettings sowie database/migrations/031_add_invoice_settings_and_
-- sequence.sql). Das Rechnungsnummern-Präfix liegt seit Migration 084 NICHT
-- mehr hier, sondern generisch für alle Dokumenttypen in
-- document_numbering_settings/document_number_sequences (siehe
-- App\Domain\Numbering\* sowie App\Infrastructure\Persistence\Pdo\
-- DocumentNumberSequence).
-- ---------------------------------------------------------------------
CREATE TABLE invoice_settings (
    id                     TINYINT       NOT NULL PRIMARY KEY DEFAULT 1,
    is_small_business      TINYINT(1)    NOT NULL DEFAULT 0,
    vat_rate_percent       DECIMAL(5, 2) NOT NULL DEFAULT 19.00,
    tax_number             VARCHAR(64)   NULL,
    vat_id                 VARCHAR(32)   NULL,
    payment_term_days      INT UNSIGNED  NOT NULL DEFAULT 14,
    bank_name              VARCHAR(255)  NULL,
    iban                   VARCHAR(34)   NULL,
    bic                    VARCHAR(11)   NULL,
    updated_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_invoice_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Firmenweite Preis-Einstellungen (Zubehörpauschale), gilt für Angebote UND
-- Rechnungen — siehe Migration 037.
CREATE TABLE pricing_settings (
    id                           TINYINT       NOT NULL PRIMARY KEY DEFAULT 1,
    accessory_flat_rate_percent  DECIMAL(5, 2) NOT NULL DEFAULT 0,
    -- Globale Rundung der Preisstaffel-Preise (siehe Migration 098,
    -- App\Domain\Settings\PriceRoundingStep/PriceRoundingDirection).
    price_rounding_step          VARCHAR(10)   NOT NULL DEFAULT 'none',
    price_rounding_direction     VARCHAR(10)   NOT NULL DEFAULT 'nearest',
    updated_at                   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_pricing_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO invoice_settings (id, is_small_business, vat_rate_percent, tax_number, vat_id, payment_term_days, bank_name, iban, bic)
VALUES (1, 0, 19.00, NULL, NULL, 14, NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- Bankverbindungen (mehrere, eine davon Standard) und Zahlungsarten mit
-- Zahlungsziel — siehe Migrationen 105/106, App\Domain\Settings\BankAccount
-- und App\Domain\Settings\PaymentMethod. Die Bank-/Zahlungsziel-Spalten in
-- invoice_settings oben sind seitdem nur noch Fallback.
-- ---------------------------------------------------------------------
CREATE TABLE bank_accounts (
    id             CHAR(36)     NOT NULL PRIMARY KEY,
    label          VARCHAR(255) NOT NULL,
    account_holder VARCHAR(255) NULL,
    bank_name      VARCHAR(255) NULL,
    iban           VARCHAR(34)  NOT NULL,
    bic            VARCHAR(11)  NULL,
    is_default     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE payment_methods (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    name              VARCHAR(255) NOT NULL,
    payment_term_days INT          NOT NULL DEFAULT 14,
    note_text         TEXT         NULL,
    is_default        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO payment_methods (id, name, payment_term_days, note_text, is_default) VALUES (UUID(), 'Überweisung', 14, NULL, 1);

-- Singleton-Zeile für pricing_settings fehlte hier bislang (siehe
-- database/migrations/037_add_pricing_settings_and_invoice_discounts.sql,
-- wo sie korrekt enthalten ist) — ohne sie wirft
-- MariadbPricingSettingsRepository::get() auf JEDER frischen Installation
-- eine RuntimeException, sobald eine Rechnung oder ein Angebot angelegt
-- wird (GetPricingSettingsHandler -> InvoiceController::renderForm()/
-- OfferController::renderForm()), weil die Tabelle sonst komplett leer ist.
INSERT INTO pricing_settings (id, accessory_flat_rate_percent, price_rounding_step, price_rounding_direction) VALUES (1, 0, 'none', 'nearest');

-- ---------------------------------------------------------------------
-- Generisches Nummerierungssystem je Dokumenttyp (Rechnungen, Angebote,
-- Mahnungen, Auftragsbestätigungen) — siehe Migration 084,
-- App\Domain\Numbering\*, App\Infrastructure\Persistence\Pdo\
-- DocumentNumberSequence.
-- ---------------------------------------------------------------------
-- "separator" ist in MySQL/MariaDB ein reserviertes Schlüsselwort (wegen
-- GROUP_CONCAT(... SEPARATOR ...)) und muss daher als Spaltenname IMMER mit
-- Backticks gequotet werden — sowohl hier als auch in jeder SQL-Abfrage, die
-- die Spalte referenziert (siehe MariadbDocumentNumberingSettingsRepository).
CREATE TABLE document_numbering_settings (
    document_type  VARCHAR(30)  NOT NULL PRIMARY KEY,
    scheme         VARCHAR(10)  NOT NULL DEFAULT 'simple',
    prefix         VARCHAR(16)  NULL,
    `separator`    VARCHAR(4)   NOT NULL DEFAULT '-',
    digit_count    TINYINT UNSIGNED NOT NULL DEFAULT 6,
    CONSTRAINT chk_document_numbering_settings_scheme CHECK (scheme IN ('simple', 'yearly'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO document_numbering_settings (document_type, scheme, prefix, `separator`, digit_count) VALUES
    ('invoice', 'simple', 'RE-', '-', 6),
    ('offer', 'simple', 'AN-', '-', 6),
    ('dunning_notice', 'simple', 'MAH-', '-', 6),
    ('order_confirmation', 'simple', 'AB-', '-', 6),
    ('customer', 'simple', 'KD-', '-', 6);

CREATE TABLE document_number_sequences (
    document_type  VARCHAR(30)  NOT NULL,
    sequence_year  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_number    INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (document_type, sequence_year)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO document_number_sequences (document_type, sequence_year, next_number) VALUES
    ('invoice', 0, 1),
    ('offer', 0, 1),
    ('dunning_notice', 0, 1),
    ('order_confirmation', 0, 1),
    ('customer', 0, 1);

-- ---------------------------------------------------------------------
-- Optionaler Einleitungs-/Schlusstext im Rechnungs-PDF-Dokument selbst
-- (siehe App\Infrastructure\Pdf\InvoicePdfGenerator), strukturell
-- identisch zu offer_pdf_settings oben (intro_html/closing_html, siehe
-- dortiger Kommentar zu Migration 064).
-- ---------------------------------------------------------------------
CREATE TABLE invoice_pdf_settings (
    id                TINYINT    NOT NULL PRIMARY KEY DEFAULT 1,
    intro_html        MEDIUMTEXT NULL,
    closing_html      MEDIUMTEXT NULL,
    updated_at        DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_invoice_pdf_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO invoice_pdf_settings (id, intro_html, closing_html) VALUES (1, NULL, NULL);

-- ---------------------------------------------------------------------
-- Preisstaffelung für die Vermietungsdauer (Aufschlag in % auf den
-- Tagesmietsatz eines Artikels, je nach Anzahl Einsatztage, 1 bis 7). Tag 1
-- entspricht per Definition 100 % und ist nicht über die Oberfläche
-- änderbar (siehe UpdatePriceTiersHandler).
-- ---------------------------------------------------------------------
CREATE TABLE price_tiers (
    day_count   TINYINT        NOT NULL PRIMARY KEY,
    percentage  DECIMAL(6, 2)  NOT NULL,
    -- Obergrenze rein technischer Natur, siehe App\Domain\Settings\PriceTier::MAX_DAY_COUNT.
    CONSTRAINT chk_price_tiers_day_count CHECK (day_count BETWEEN 1 AND 60)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO price_tiers (day_count, percentage) VALUES
    (1, 100.00),
    (2, 150.00),
    (3, 175.00),
    (4, 190.00),
    (5, 200.00),
    (6, 210.00),
    (7, 220.00);

-- Standard-Administrator (E-Mail: admin@materialverleih.local, Passwort:
-- ChangeMe!2026) — unbedingt nach der ersten Anmeldung ändern, siehe
-- must_change_password.
INSERT INTO users (id, email, password_hash, permissions, two_factor_method, must_change_password, is_active)
VALUES (
    '00000000-0000-4000-8000-000000000001',
    'admin@materialverleih.local',
    '$2y$12$9DnE/1ApuLo1TWso1Maa.OwZMg4UP.AjQQJa4ncGM3ka/fF7K4Y9q',
    'administration',
    'none',
    1,
    1
);

-- ---------------------------------------------------------------------
-- Passkey-/WebAuthn-Anmeldung: zusätzlicher, gleichwertiger Anmeldeweg
-- neben Passwort (+ optionalem zweitem Faktor). webauthn_credentials hält
-- die pro Benutzer registrierten Authenticatoren (Public Key im
-- COSE-Format als JSON, siehe App\Infrastructure\WebAuthn\WebAuthnService),
-- webauthn_challenges die kurzlebigen, genau einmal verwendbaren
-- Challenges für Registrierung und Anmeldung (siehe
-- App\Domain\Auth\WebauthnChallenge).
-- ---------------------------------------------------------------------
CREATE TABLE webauthn_credentials (
    id                VARCHAR(36)   NOT NULL PRIMARY KEY,
    user_id           VARCHAR(36)   NOT NULL,
    credential_id     VARCHAR(1024) NOT NULL,
    public_key_cose   TEXT          NOT NULL,
    sign_count        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    nickname          VARCHAR(100)  NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at      DATETIME      NULL,
    CONSTRAINT fk_webauthn_credentials_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uq_webauthn_credentials_credential_id UNIQUE (credential_id(255))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_webauthn_credentials_user_id ON webauthn_credentials (user_id);

CREATE TABLE webauthn_challenges (
    id           VARCHAR(36)  NOT NULL PRIMARY KEY,
    user_id      VARCHAR(36)  NULL,
    challenge    VARCHAR(255) NOT NULL,
    type         VARCHAR(20)  NOT NULL,
    expires_at   DATETIME     NOT NULL,
    consumed_at  DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_webauthn_challenges_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uq_webauthn_challenges_challenge UNIQUE (challenge),
    CONSTRAINT chk_webauthn_challenges_type CHECK (type IN ('registration', 'authentication'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_webauthn_challenges_expires_at ON webauthn_challenges (expires_at);

-- Kurzlebige, einmal verwendbare Tokens zum (Erst-)Festlegen eines
-- Passworts: sowohl für die Admin-Einladung eines Benutzers ohne
-- Erstpasswort als auch für "Passwort vergessen" (siehe
-- App\Application\Auth\PasswordSetupTokenIssuer und
-- App\Infrastructure\Http\Controllers\SetupPasswordController). Es wird nur
-- der Hash des Tokens gespeichert (analog zu Passwörtern), nicht der
-- Klartext-Token selbst.
CREATE TABLE password_setup_tokens (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    user_id      CHAR(36)     NOT NULL,
    token_hash   VARCHAR(255) NOT NULL,
    expires_at   DATETIME     NOT NULL,
    used_at      DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_setup_tokens_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uq_password_setup_tokens_token_hash UNIQUE (token_hash),
    INDEX idx_password_setup_tokens_user (user_id),
    INDEX idx_password_setup_tokens_expires_at (expires_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Etikettendrucker (Netzwerkdrucker, z. B. Brother P-touch): siehe
-- App\Domain\Printing\LabelPrinter und App\Infrastructure\Printing\* für
-- die austauschbare Treiberabstraktion je Druckermodell.
-- ---------------------------------------------------------------------
CREATE TABLE label_printers (
    id            VARCHAR(36)  NOT NULL PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    driver_type   VARCHAR(50)  NOT NULL,
    ip_address    VARCHAR(45)  NOT NULL,
    port          SMALLINT UNSIGNED NOT NULL DEFAULT 9100,
    tape_width_mm SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Angebote (Kostenvoranschläge) — eigene, von rentals unabhängige Entität
-- (siehe database/migrations/021_add_offers.sql für die Begründung der
-- einzelnen Spalten).
-- ---------------------------------------------------------------------
CREATE TABLE offers (
    id                    CHAR(36)      NOT NULL PRIMARY KEY,
    -- Siehe Migration 084 / App\Infrastructure\Persistence\Pdo\
    -- DocumentNumberSequence — wird beim Anlegen sofort vergeben, anders
    -- als invoices.invoice_number daher NOT NULL.
    offer_number          VARCHAR(50)   NOT NULL,
    customer_id           CHAR(36)      NULL,
    contact_id            CHAR(36)      NULL, -- Ansprechpartner-Auswahl, siehe Migration 104
    payment_method_id     CHAR(36)      NULL, -- Zahlungsart, NULL = Standard-Zahlungsart (Migration 106)
    event_location        VARCHAR(255)  NULL,
    venue_id              CHAR(36)      NULL,
    start_date            DATE          NULL, -- optional seit Migration 102
    end_date              DATE          NULL, -- optional seit Migration 102
    valid_until           DATE          NULL,
    status                VARCHAR(20)   NOT NULL DEFAULT 'draft',
    note                  TEXT          NULL,
    -- Freitext ("Schlusstext"), erscheint NACH der Positionsliste/Summe
    -- (anders als note oben, siehe Migration 092).
    closing_text          MEDIUMTEXT    NULL,
    -- Zubehörpauschale (Snapshot) + optionaler Kombirabatt, siehe Migration
    -- 038 / App\Domain\Pricing\DocumentTotals.
    accessory_flat_rate_enabled TINYINT(1)    NOT NULL DEFAULT 1,
    accessory_flat_rate_percent DECIMAL(5, 2) NULL,
    discount_type         ENUM('percent', 'fixed') NULL,
    discount_value        DECIMAL(10, 2) NULL,
    converted_rental_id   CHAR(36)      NULL,
    -- Optionale Verknüpfung mit einer bereits bestehenden Veranstaltung
    -- (Rental) — NICHT zu verwechseln mit converted_rental_id oben (das
    -- wird erst NACH dem Umwandeln gesetzt). Siehe Migration 103 / App\
    -- Domain\Offer\Offer::$sourceRentalId.
    source_rental_id      CHAR(36)      NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_offers_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_offers_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    CONSTRAINT fk_offers_converted_rental
        FOREIGN KEY (converted_rental_id) REFERENCES rentals (id) ON DELETE SET NULL,
    CONSTRAINT fk_offers_source_rental
        FOREIGN KEY (source_rental_id) REFERENCES rentals (id) ON DELETE SET NULL,
    CONSTRAINT fk_offers_contact
        FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL,
    CONSTRAINT fk_offers_payment_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL,
    CONSTRAINT uq_offers_offer_number UNIQUE (offer_number),
    INDEX idx_offers_customer (customer_id),
    INDEX idx_offers_venue (venue_id),
    INDEX idx_offers_status (status),
    INDEX idx_offers_dates (start_date, end_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE offer_items (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    offer_id            CHAR(36)      NOT NULL,
    article_id          CHAR(36)      NOT NULL,
    quantity            INT UNSIGNED  NOT NULL,
    unit_price_per_day  DECIMAL(10, 2) NOT NULL,
    line_note           VARCHAR(255)  NULL,
    -- Gemeinsame Reihenfolge mit offer_free_text_items/offer_section_texts
    -- (orderedRows()-Konzept, siehe Migration 085/OfferDetail::orderedRows()).
    sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
    CONSTRAINT fk_offer_items_offer
        FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    CONSTRAINT fk_offer_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_offer_items_offer (offer_id),
    INDEX idx_offer_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Freitext-Posten und Zwischentexte (Zwischenüberschriften ohne Preis) —
-- gemeinsam mit den regulären Mietpositionen frei anordenbar über
-- sort_order (orderedRows()-Konzept, siehe Migration 085). Arbeitszeit-/
-- Verkaufspositionen bleiben davon bewusst unberührt (siehe OfferDetail-
-- Klassendoc).
CREATE TABLE offer_free_text_items (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    offer_id            CHAR(36)      NOT NULL,
    description         VARCHAR(255)  NOT NULL,
    quantity            DECIMAL(10, 2) NOT NULL DEFAULT 1,
    unit_price          DECIMAL(10, 2) NOT NULL DEFAULT 0,
    sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
    CONSTRAINT fk_offer_free_text_items_offer
        FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    INDEX idx_offer_free_text_items_offer (offer_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE offer_section_texts (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    offer_id            CHAR(36)      NOT NULL,
    heading             VARCHAR(255)  NOT NULL,
    sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
    CONSTRAINT fk_offer_section_texts_offer
        FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    INDEX idx_offer_section_texts_offer (offer_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Arbeitszeit-Positionen eines Angebots — strukturell analog zu
-- offer_items, aber mit hours statt quantity und unit_rate_per_hour (beim
-- Hinzufügen gesnapshoteter Stundensatz, siehe labor_time_articles) statt
-- eines frei editierbaren unit_price_per_day. Erscheint auf Angebot/
-- Rechnung immer NACH dem Kombirabatt (siehe App\Domain\Pricing\
-- DocumentTotals).
CREATE TABLE offer_labor_items (
    id                      CHAR(36)       NOT NULL PRIMARY KEY,
    offer_id                CHAR(36)       NOT NULL,
    labor_time_article_id   CHAR(36)       NOT NULL,
    user_id                 CHAR(36)       NULL, -- Mitarbeiter/Benutzer, siehe Migration 097; optional
    hours                   DECIMAL(6, 2)  NOT NULL,
    unit_rate_per_hour      DECIMAL(10, 2) NOT NULL,
    billing_unit            ENUM('hour', 'day') NOT NULL DEFAULT 'hour', -- siehe Migration 101
    CONSTRAINT fk_offer_labor_items_offer
        FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    CONSTRAINT fk_offer_labor_items_labor_time_article
        FOREIGN KEY (labor_time_article_id) REFERENCES labor_time_articles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_offer_labor_items_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_offer_labor_items_offer (offer_id),
    INDEX idx_offer_labor_items_labor_time_article (labor_time_article_id),
    INDEX idx_offer_labor_items_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Auftragsbestätigungen — vereinfachte Geschwister-Entität zu Offer (siehe
-- Migration 084 für die ausführliche Begründung des reduzierten
-- Funktionsumfangs gegenüber Offer: kein validUntil, keine Arbeitszeit-/
-- Verkaufspositionen, kein E-Mail-Workflow).
-- ---------------------------------------------------------------------
CREATE TABLE order_confirmations (
    id                    CHAR(36)      NOT NULL PRIMARY KEY,
    order_confirmation_number VARCHAR(50) NOT NULL,
    customer_id           CHAR(36)      NULL,
    contact_id            CHAR(36)      NULL, -- Ansprechpartner-Auswahl, siehe Migration 104
    payment_method_id     CHAR(36)      NULL, -- Zahlungsart, NULL = Standard-Zahlungsart (Migration 106)
    event_location        VARCHAR(255)  NULL,
    venue_id              CHAR(36)      NULL,
    start_date            DATE          NULL, -- optional seit Migration 102
    end_date              DATE          NULL, -- optional seit Migration 102
    status                VARCHAR(20)   NOT NULL DEFAULT 'draft',
    note                  TEXT          NULL,
    -- Freitext ("Schlusstext"), erscheint NACH der Positionsliste/Summe
    -- (anders als note oben, siehe Migration 092).
    closing_text          MEDIUMTEXT    NULL,
    accessory_flat_rate_enabled TINYINT(1)    NOT NULL DEFAULT 1,
    accessory_flat_rate_percent DECIMAL(5, 2) NULL,
    discount_type         ENUM('percent', 'fixed') NULL,
    discount_value        DECIMAL(10, 2) NULL,
    source_offer_id       CHAR(36)      NULL,
    -- Optionale Verknüpfung mit einer bereits bestehenden Veranstaltung
    -- (Rental) — siehe Migration 103 / App\Domain\OrderConfirmation\
    -- OrderConfirmation::$sourceRentalId.
    source_rental_id      CHAR(36)      NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_order_confirmations_number UNIQUE (order_confirmation_number),
    CONSTRAINT fk_order_confirmations_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_order_confirmations_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    CONSTRAINT fk_order_confirmations_source_offer
        FOREIGN KEY (source_offer_id) REFERENCES offers (id) ON DELETE SET NULL,
    CONSTRAINT fk_order_confirmations_source_rental
        FOREIGN KEY (source_rental_id) REFERENCES rentals (id) ON DELETE SET NULL,
    CONSTRAINT fk_order_confirmations_contact
        FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL,
    CONSTRAINT fk_order_confirmations_payment_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL,
    INDEX idx_order_confirmations_customer (customer_id),
    INDEX idx_order_confirmations_venue (venue_id),
    INDEX idx_order_confirmations_status (status),
    INDEX idx_order_confirmations_dates (start_date, end_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE order_confirmation_items (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    order_confirmation_id CHAR(36)    NOT NULL,
    article_id          CHAR(36)      NOT NULL,
    quantity            INT UNSIGNED  NOT NULL,
    unit_price_per_day  DECIMAL(10, 2) NOT NULL,
    line_note           VARCHAR(255)  NULL,
    sort_order           INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_order_confirmation_items_oc
        FOREIGN KEY (order_confirmation_id) REFERENCES order_confirmations (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_confirmation_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_order_confirmation_items_oc (order_confirmation_id),
    INDEX idx_order_confirmation_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE order_confirmation_free_text_items (
    id                     CHAR(36)      NOT NULL PRIMARY KEY,
    order_confirmation_id  CHAR(36)      NOT NULL,
    description            VARCHAR(255)  NOT NULL,
    quantity                DECIMAL(10, 2) NOT NULL DEFAULT 1,
    unit_price              DECIMAL(10, 2) NOT NULL DEFAULT 0,
    sort_order              INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_order_confirmation_free_text_items_oc
        FOREIGN KEY (order_confirmation_id) REFERENCES order_confirmations (id) ON DELETE CASCADE,
    INDEX idx_order_confirmation_free_text_items_oc (order_confirmation_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE order_confirmation_section_texts (
    id                     CHAR(36)      NOT NULL PRIMARY KEY,
    order_confirmation_id  CHAR(36)      NOT NULL,
    heading                VARCHAR(255)  NOT NULL,
    sort_order              INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_order_confirmation_section_texts_oc
        FOREIGN KEY (order_confirmation_id) REFERENCES order_confirmations (id) ON DELETE CASCADE,
    INDEX idx_order_confirmation_section_texts_oc (order_confirmation_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Rechnungen — eigene, von offers und rentals unabhängige Entität (siehe
-- database/migrations/033_add_invoices.sql für die ausführliche Begründung
-- der einzelnen Spalten).
-- ---------------------------------------------------------------------
CREATE TABLE invoices (
    id                  CHAR(36)       NOT NULL PRIMARY KEY,
    customer_id         CHAR(36)       NULL,
    contact_id          CHAR(36)       NULL, -- Ansprechpartner-Auswahl, siehe Migration 104
    bank_account_id     CHAR(36)       NULL, -- gewählte Bankverbindung, NULL = Standardkonto (Migration 105)
    payment_method_id   CHAR(36)       NULL, -- Zahlungsart, NULL = Standard-Zahlungsart (Migration 106)
    event_location      VARCHAR(255)   NULL,
    venue_id            CHAR(36)       NULL,
    start_date          DATE           NULL, -- optional seit Migration 102
    end_date            DATE           NULL, -- optional seit Migration 102
    invoice_number      VARCHAR(32)    NULL UNIQUE,
    invoice_date        DATE           NULL,
    due_date             DATE          NULL,
    buyer_reference     VARCHAR(255)   NULL,
    status              VARCHAR(20)    NOT NULL DEFAULT 'draft',
    is_small_business   TINYINT(1)     NULL,
    vat_rate_percent    DECIMAL(5, 2)  NULL,
    -- Zubehörpauschale (Snapshot) + optionaler Kombirabatt, siehe Migration
    -- 037 / App\Domain\Pricing\DocumentTotals.
    accessory_flat_rate_enabled TINYINT(1)    NOT NULL DEFAULT 1,
    accessory_flat_rate_percent DECIMAL(5, 2) NULL,
    -- Pauschalpreis für die Mietpositionen (siehe Migration 094): ist
    -- flat_rate_enabled gesetzt, erscheinen die Mietpositionen auf der
    -- Rechnung ohne Einzelpreise, stattdessen zählt flat_rate_amount als
    -- Zwischensumme der Mietpreise (App\Domain\Pricing\DocumentTotals).
    -- Verkaufs-/Sonstige Positionen (invoice_items.is_rental_item = 0) sind
    -- davon unberührt und werden weiterhin regulär einzeln bepreist.
    flat_rate_enabled   TINYINT(1)     NOT NULL DEFAULT 0,
    flat_rate_amount    DECIMAL(10, 2) NULL,
    discount_type       ENUM('percent', 'fixed') NULL,
    discount_value      DECIMAL(10, 2) NULL,
    shipping_amount     DECIMAL(10, 2) NULL, -- optionale Versandkosten, siehe database/migrations/072_add_inventory_number_sales.sql
    paid_at             DATETIME       NULL,
    note                TEXT           NULL,
    -- Freitext ("Schlusstext"), erscheint NACH der Positionsliste/Summe
    -- (anders als note oben, siehe Migration 092).
    closing_text        MEDIUMTEXT     NULL,
    source_offer_id     CHAR(36)       NULL,
    source_rental_id    CHAR(36)       NULL,
    -- Logistik bei Vermietungen (siehe Migration 094): logistics_method
    -- unterscheidet Selbstabholung durch den Kunden ('pickup') von
    -- Lieferung durch uns ('delivery') — bei 'pickup' sind date1/date2
    -- Abhol-/Rückgabetermin DES KUNDEN, bei 'delivery' Liefer-/
    -- Abholtermin DURCH UNS (siehe Invoice-Klassen-Doc).
    logistics_method    ENUM('pickup', 'delivery') NULL,
    logistics_date1     DATETIME       NULL,
    logistics_date2     DATETIME       NULL,
    created_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoices_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_source_offer
        FOREIGN KEY (source_offer_id) REFERENCES offers (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_source_rental
        FOREIGN KEY (source_rental_id) REFERENCES rentals (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_contact
        FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_bank_account
        FOREIGN KEY (bank_account_id) REFERENCES bank_accounts (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_payment_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL,
    INDEX idx_invoices_customer (customer_id),
    INDEX idx_invoices_venue (venue_id),
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_dates (start_date, end_date),
    INDEX idx_invoices_number (invoice_number)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    invoice_id          CHAR(36)      NOT NULL,
    article_id          CHAR(36)      NOT NULL,
    quantity            INT UNSIGNED  NOT NULL,
    unit_price_per_day  DECIMAL(10, 2) NOT NULL,
    line_note           VARCHAR(255)  NULL,
    -- Mietposition (true, Standard/Bestand) vs. Verkaufs-/Sonstige Position
    -- (false) — siehe Migration 094 + Invoice::$flatRateEnabled-Klassendoc.
    is_rental_item      TINYINT(1)    NOT NULL DEFAULT 1,
    -- Gemeinsame Reihenfolge mit invoice_free_text_items/invoice_section_texts
    -- (orderedRows()-Konzept, siehe Migration 085/InvoiceDetail::orderedRows()).
    sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
    CONSTRAINT fk_invoice_items_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_invoice_items_invoice (invoice_id),
    INDEX idx_invoice_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Freitext-Posten und Zwischentexte — siehe offer_free_text_items/
-- offer_section_texts für die identische Begründung.
CREATE TABLE invoice_free_text_items (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    invoice_id          CHAR(36)      NOT NULL,
    description         VARCHAR(255)  NOT NULL,
    quantity            DECIMAL(10, 2) NOT NULL DEFAULT 1,
    unit_price          DECIMAL(10, 2) NOT NULL DEFAULT 0,
    sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
    CONSTRAINT fk_invoice_free_text_items_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    INDEX idx_invoice_free_text_items_invoice (invoice_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE invoice_section_texts (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    invoice_id          CHAR(36)      NOT NULL,
    heading             VARCHAR(255)  NOT NULL,
    sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
    CONSTRAINT fk_invoice_section_texts_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    INDEX idx_invoice_section_texts_invoice (invoice_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Arbeitszeit-Positionen einer Rechnung — strukturell analog zu
-- invoice_items, aber mit hours statt quantity und unit_rate_per_hour (beim
-- Hinzufügen gesnapshoteter Stundensatz, siehe labor_time_articles) statt
-- eines frei editierbaren unit_price_per_day. Erscheint auf der Rechnung
-- immer NACH dem Kombirabatt (siehe App\Domain\Pricing\DocumentTotals).
CREATE TABLE invoice_labor_items (
    id                      CHAR(36)       NOT NULL PRIMARY KEY,
    invoice_id              CHAR(36)       NOT NULL,
    labor_time_article_id   CHAR(36)       NOT NULL,
    user_id                 CHAR(36)       NULL, -- Mitarbeiter/Benutzer, siehe Migration 097; optional
    hours                   DECIMAL(6, 2)  NOT NULL,
    unit_rate_per_hour      DECIMAL(10, 2) NOT NULL,
    billing_unit            ENUM('hour', 'day') NOT NULL DEFAULT 'hour', -- siehe Migration 101
    CONSTRAINT fk_invoice_labor_items_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_labor_items_labor_time_article
        FOREIGN KEY (labor_time_article_id) REFERENCES labor_time_articles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoice_labor_items_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_invoice_labor_items_invoice (invoice_id),
    INDEX idx_invoice_labor_items_labor_time_article (labor_time_article_id),
    INDEX idx_invoice_labor_items_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Verkauf von Artikel-Exemplaren (siehe database/migrations/
-- 072_add_inventory_number_sales.sql für den ursprünglichen, auf einzelne
-- Inventurnummern beschränkten Verkauf, und 078_add_purchase_orders_and_
-- sale_stock.sql für die spätere Erweiterung um den Direktverkauf aus dem
-- reinen Verkaufsbestand, siehe App\Domain\Sale\Sale). inventory_number_id
-- ist NULLable: gesetzt = Verkauf eines gebrauchten Exemplars aus dem
-- Vermietpool (quantity immer 1), NULL = Verkauf aus dem reinen
-- Verkaufsbestand (quantity = verkaufte Menge, siehe sale_stock_lots/
-- sale_stock_consumptions). cost_of_goods_sold ist der zum Verkaufszeitpunkt
-- gesnapshotete Einkaufswert (Vermietpool: InventoryNumber::
-- effectivePurchasePrice(), Verkaufsbestand: FIFO-Summe) für die
-- Margen-Berechnung.
-- ---------------------------------------------------------------------
CREATE TABLE sales (
    id                  CHAR(36)       NOT NULL PRIMARY KEY,
    inventory_number_id CHAR(36)       NULL,
    article_id          CHAR(36)       NOT NULL,
    quantity            INT UNSIGNED   NOT NULL DEFAULT 1,
    customer_id         CHAR(36)       NULL,
    invoice_id          CHAR(36)       NOT NULL,
    sale_price          DECIMAL(10, 2) NOT NULL,
    cost_of_goods_sold  DECIMAL(10, 2) NULL,
    shipping_amount     DECIMAL(10, 2) NULL,
    sold_at             DATETIME       NOT NULL,
    note                TEXT           NULL,
    created_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Erlaubt beliebig viele NULL-Werte (Bestandsverkäufe ohne Exemplar) —
    -- nur ein TATSÄCHLICHES Exemplar darf höchstens einmal verkauft werden.
    CONSTRAINT uq_sales_inventory_number UNIQUE (inventory_number_id),
    CONSTRAINT fk_sales_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id),
    CONSTRAINT fk_sales_article
        FOREIGN KEY (article_id) REFERENCES articles (id),
    CONSTRAINT fk_sales_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Aktive Anmelde-Sitzungen (serverseitiges Register neben der dateibasierten
-- PHP-Session selbst, siehe App\Domain\Auth\UserSession sowie
-- database/migrations/025_add_user_sessions.sql für die ausführliche
-- Begründung).
-- ---------------------------------------------------------------------
CREATE TABLE user_sessions (
    id            VARCHAR(128) NOT NULL PRIMARY KEY,
    user_id       VARCHAR(36)  NOT NULL,
    user_agent    TEXT         NULL,
    ip_address    VARCHAR(45)  NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_user_sessions_user_id (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Admin-bearbeitbare Vorlagen (Betreff + HTML-Inhalt) für alle vom System
-- versendeten E-Mails, siehe App\Domain\Mail\EmailTemplateKey sowie
-- database/migrations/028_add_email_templates.sql für die ausführliche
-- Begründung. Bewusst NICHT dabei: die SMTP-Test-Mail.
-- ---------------------------------------------------------------------
CREATE TABLE email_templates (
    template_key VARCHAR(64)   NOT NULL PRIMARY KEY,
    subject      VARCHAR(255)  NOT NULL,
    body_html    MEDIUMTEXT    NOT NULL,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO email_templates (template_key, subject, body_html) VALUES
('login_otp', 'Ihr Anmeldecode für {{company_name}}',
 '<p>Ihr Anmeldecode lautet: <strong>{{code}}</strong></p>\n<p>Der Code ist {{valid_minutes}} Minuten gültig. Falls Sie diesen Code nicht angefordert haben, ignorieren Sie diese E-Mail.</p>'),
('password_setup_invite', 'Ihr Zugang zur {{company_name}}-Verwaltung',
 '<p>Hallo,</p>\n<p>für Sie wurde ein Benutzerkonto in der {{company_name}}-Verwaltung angelegt. Bitte legen Sie über folgenden Link Ihr Passwort fest:</p>\n<p><a href="{{link}}">{{link}}</a></p>\n<p>Der Link ist {{valid_hours}} Stunden gültig.</p>'),
('password_reset', 'Passwort zurücksetzen',
 '<p>Hallo,</p>\n<p>für Ihr Konto in der {{company_name}}-Verwaltung wurde ein neues Passwort angefordert. Über folgenden Link können Sie ein neues Passwort festlegen:</p>\n<p><a href="{{link}}">{{link}}</a></p>\n<p>Der Link ist {{valid_hours}} Stunden gültig. Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren — es ändert sich nichts an Ihrem Konto.</p>'),
('offer_pdf', 'Ihr Angebot',
 '<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie unser Angebot.</p>\n<p>{{location_line}}Zeitraum: {{date_range}}<br>{{valid_until_line}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>'),
('packing_list_pdf', 'Packliste für Ihre Veranstaltung',
 '<p>Guten Tag,</p>\n<p>im Anhang finden Sie die Packliste für folgende Veranstaltung:</p>\n<p>Veranstaltung: {{event_name}}<br>Kunde: {{customer_name}}<br>{{location_line}}Zeitraum: {{date_range}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>'),
('invoice_pdf', 'Ihre Rechnung {{invoice_number}}',
 '<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie Ihre Rechnung {{invoice_number}} (inkl. XRechnung-XML zur elektronischen Weiterverarbeitung).</p>\n<p>{{location_line}}Leistungszeitraum: {{date_range}}<br>Fällig am: {{due_date}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>'),
('dunning_notice', '{{dunning_level_name}}: Rechnung {{invoice_number}}',
 '<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie eine {{dunning_level_name}} zu unserer Rechnung {{invoice_number}}, die am {{due_date}} fällig war.</p>\n<p>Offener Gesamtbetrag: {{total_due}}</p>\n<p>Bitte begleichen Sie den Betrag umgehend. Sollte dies bereits geschehen sein, betrachten Sie diese E-Mail als gegenstandslos.</p>\n<p>Mit freundlichen Grüßen</p>');

-- ---------------------------------------------------------------------
-- Browser-Push-Benachrichtigungen (Web Push, RFC 8291/8292/8188),
-- zusätzlich zur E-Mail-Benachrichtigung bei neuen Beleg-Posteingang-
-- Nachrichten (siehe users.notify_on_new_receipt_email, dasselbe Opt-in-
-- Feld steuert beide Zustellarten). push_settings ist eine Singleton-
-- Tabelle (id = 1) mit dem EINEN VAPID-Schlüsselpaar der gesamten
-- Anwendung (nicht pro Benutzer), automatisch per ext-openssl erzeugt.
-- ---------------------------------------------------------------------
CREATE TABLE push_settings (
    id                     TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    vapid_public_key       VARCHAR(255) NULL,
    vapid_private_key_pem  TEXT         NULL,
    subject_email          VARCHAR(255) NULL,
    updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_push_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO push_settings (id, vapid_public_key, vapid_private_key_pem, subject_email)
VALUES (1, NULL, NULL, NULL);

-- Ein Benutzer kann mehrere Push-Subscriptions besitzen (mehrere
-- Geräte/Browser gleichzeitig abonniert).
CREATE TABLE push_subscriptions (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    user_id      CHAR(36)     NOT NULL,
    endpoint     VARCHAR(500) NOT NULL,
    p256dh       VARCHAR(255) NOT NULL,
    auth         VARCHAR(255) NOT NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   DATETIME     NOT NULL,
    CONSTRAINT fk_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uq_push_subscriptions_endpoint UNIQUE (endpoint)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_push_subscriptions_user_id ON push_subscriptions (user_id);

-- ---------------------------------------------------------------------
-- Kunden-Benachrichtigungen/-Erinnerungen über WhatsApp Business (Meta
-- Cloud API), Telegram (Bot-API) und Signal (signal-cli-rest-api) — siehe
-- App\Domain\Messaging, App\Infrastructure\Messaging\HttpMessageSender
-- sowie Migration 077 für die ausführliche Begründung der einzelnen
-- Spalten (insbesondere die bewusste Trennung von
-- whatsapp_business_settings, die NUR den Produktkatalog steuert).
-- ---------------------------------------------------------------------
CREATE TABLE messaging_settings (
    id                            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    whatsapp_enabled              TINYINT(1)   NOT NULL DEFAULT 0,
    whatsapp_phone_number_id      VARCHAR(64)  NULL,
    whatsapp_access_token         VARCHAR(512) NULL,
    whatsapp_business_account_id  VARCHAR(64)  NULL,
    telegram_enabled              TINYINT(1)   NOT NULL DEFAULT 0,
    telegram_bot_token            VARCHAR(255) NULL,
    signal_enabled                TINYINT(1)   NOT NULL DEFAULT 0,
    signal_api_base_url           VARCHAR(255) NULL,
    signal_sender_number          VARCHAR(50)  NULL,
    updated_at                    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_messaging_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO messaging_settings (id) VALUES (1);

CREATE TABLE message_templates (
    id                       CHAR(36)     NOT NULL PRIMARY KEY,
    channel                  VARCHAR(20)  NOT NULL,
    template_key             VARCHAR(50)  NOT NULL,
    body_text                TEXT         NULL,
    meta_template_name       VARCHAR(255) NULL,
    meta_template_language   VARCHAR(10)  NULL DEFAULT 'de',
    created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_message_templates_channel_key (channel, template_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Ziellaufwerke (externe Speicherziele: WebDAV/Nextcloud, FTPS, SFTP, SCP,
-- S3-kompatibler Objektspeicher) für den automatischen, synchronen Push
-- fertiger Dokumente (Rechnung/Angebot/Packliste/Beleg) — siehe
-- App\Infrastructure\DocumentStorage\DocumentStoragePusher sowie
-- database/migrations/044_add_document_storage_targets.sql für die
-- ausführliche Begründung der einzelnen Spalten.
-- ---------------------------------------------------------------------
CREATE TABLE document_storage_targets (
    id                       CHAR(36)      NOT NULL PRIMARY KEY,
    name                     VARCHAR(255)  NOT NULL,
    protocol                 ENUM('webdav', 'ftps', 'sftp', 'scp', 's3', 'nextcloud', 'owncloud') NOT NULL,
    host                     VARCHAR(255)  NULL,
    port                     SMALLINT UNSIGNED NULL,
    username                 VARCHAR(255)  NULL,
    password                 VARCHAR(255)  NULL,
    private_key              TEXT          NULL,
    private_key_passphrase   VARCHAR(255)  NULL,
    base_path                VARCHAR(500)  NULL,
    webdav_use_https         TINYINT(1)    NOT NULL DEFAULT 1,
    webdav_allow_self_signed TINYINT(1)    NOT NULL DEFAULT 0,
    s3_access_key_id         VARCHAR(255)  NULL,
    s3_secret_access_key     VARCHAR(255)  NULL,
    s3_bucket                VARCHAR(255)  NULL,
    s3_region                VARCHAR(64)   NULL DEFAULT 'eu-central-1',
    s3_endpoint              VARCHAR(255)  NULL,
    s3_use_path_style        TINYINT(1)    NOT NULL DEFAULT 0,
    -- Ordner für Veranstaltungen/Kunden (siehe database/migrations/148_storage_folders.sql).
    folders_enabled          TINYINT(1)    NOT NULL DEFAULT 0,
    event_folder_template    VARCHAR(500)  NOT NULL DEFAULT 'Veranstaltungen/{year}/{date} {event_name}',
    customer_folder_template VARCHAR(500)  NOT NULL DEFAULT 'Kunden/{customer_number} {customer_name}',
    event_subfolder_template VARCHAR(500)  NOT NULL DEFAULT '{date} {event_name}',
    share_always_password    TINYINT(1)    NOT NULL DEFAULT 0,
    share_password_length    TINYINT UNSIGNED NOT NULL DEFAULT 16,
    share_password_uppercase TINYINT(1)    NOT NULL DEFAULT 1,
    share_password_lowercase TINYINT(1)    NOT NULL DEFAULT 1,
    share_password_digits    TINYINT(1)    NOT NULL DEFAULT 1,
    share_password_symbols   TINYINT(1)    NOT NULL DEFAULT 0,
    enabled                  TINYINT(1)    NOT NULL DEFAULT 1,
    created_at               DATETIME      NOT NULL,
    updated_at               DATETIME      NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Zuordnung Dokumenttyp -> Ziellaufwerk(e) + Ablagepfad-Vorlage (siehe
-- database/migrations/045_add_document_storage_assignments.sql).
CREATE TABLE document_storage_assignments (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    document_type     ENUM('invoice', 'offer', 'packing_list', 'receipt', 'dunning_notice') NOT NULL,
    storage_target_id CHAR(36)     NOT NULL,
    target_path       VARCHAR(500) NOT NULL,
    enabled           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at        DATETIME     NOT NULL,
    CONSTRAINT fk_document_storage_assignments_target
        FOREIGN KEY (storage_target_id) REFERENCES document_storage_targets (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_document_storage_assignments_document_type ON document_storage_assignments (document_type);

-- Protokoll jedes einzelnen (Versuchs eines) synchronen Datei-Pushs (siehe
-- database/migrations/046_add_document_storage_upload_log.sql).
CREATE TABLE document_storage_upload_log (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    document_type     ENUM('invoice', 'offer', 'packing_list', 'receipt', 'dunning_notice') NOT NULL,
    source_id         CHAR(36)     NOT NULL,
    assignment_id     CHAR(36)     NULL,
    storage_target_id CHAR(36)     NULL,
    remote_path       VARCHAR(500) NOT NULL,
    status            ENUM('success', 'failed') NOT NULL,
    error_message     VARCHAR(1000) NULL,
    created_at        DATETIME     NOT NULL,
    CONSTRAINT fk_document_storage_upload_log_assignment
        FOREIGN KEY (assignment_id) REFERENCES document_storage_assignments (id) ON DELETE SET NULL,
    CONSTRAINT fk_document_storage_upload_log_target
        FOREIGN KEY (storage_target_id) REFERENCES document_storage_targets (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_document_storage_upload_log_document ON document_storage_upload_log (document_type, source_id);
CREATE INDEX idx_document_storage_upload_log_created_at ON document_storage_upload_log (created_at);

-- Ordner je Veranstaltung/Kunde im externen Dateiablagesystem samt Freigabe
-- (siehe database/migrations/148_storage_folders.sql).
CREATE TABLE storage_folders (
    id                 CHAR(36)     NOT NULL PRIMARY KEY,
    -- Genau eine der beiden Spalten ist gesetzt (Veranstaltung ODER Kunde).
    rental_id          CHAR(36)     NULL,
    customer_id        CHAR(36)     NULL,
    storage_target_id  CHAR(36)     NOT NULL,
    -- Aufgelöster Ordnerpfad relativ zum Basis-Pfad des Ziellaufwerks. Wird
    -- beim Anlegen festgeschrieben und bei späteren Umbenennungen der
    -- Veranstaltung/des Kunden bewusst nicht mitgezogen.
    path               VARCHAR(700) NOT NULL,
    in_customer_folder TINYINT(1)   NOT NULL DEFAULT 0,
    share_id           VARCHAR(64)  NULL,
    share_url          VARCHAR(500) NULL,
    share_permission   ENUM('read', 'edit', 'upload') NULL,
    share_has_password TINYINT(1)   NOT NULL DEFAULT 0,
    share_mailed_at    DATETIME     NULL,
    share_mailed_to    VARCHAR(255) NULL,
    created_at         DATETIME     NOT NULL,
    updated_at         DATETIME     NOT NULL,
    UNIQUE KEY uq_storage_folders_rental (rental_id),
    UNIQUE KEY uq_storage_folders_customer (customer_id),
    CONSTRAINT fk_storage_folders_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_storage_folders_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_storage_folders_target FOREIGN KEY (storage_target_id) REFERENCES document_storage_targets (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Mahnwesen (siehe database/migrations/052_add_dunning.sql für die
-- ausführliche Begründung, insbesondere das Snapshot-Prinzip).
-- ---------------------------------------------------------------------
CREATE TABLE dunning_levels (
    id             CHAR(36)       NOT NULL PRIMARY KEY,
    name           VARCHAR(255)   NOT NULL,
    days_after_due INT            NOT NULL,
    fee_type       ENUM('none', 'fixed', 'percent') NOT NULL DEFAULT 'none',
    fee_value      DECIMAL(10, 2) NULL,
    created_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dunning_levels_days_after_due (days_after_due)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO dunning_levels (id, name, days_after_due, fee_type, fee_value) VALUES
    (UUID(), 'Zahlungserinnerung', 7, 'none', NULL),
    (UUID(), '1. Mahnung', 14, 'fixed', 5.00),
    (UUID(), '2. Mahnung', 28, 'fixed', 10.00);

CREATE TABLE dunning_settings (
    id                TINYINT    NOT NULL PRIMARY KEY DEFAULT 1,
    auto_send_enabled TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT chk_dunning_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO dunning_settings (id, auto_send_enabled) VALUES (1, 0);

CREATE TABLE dunning_notices (
    id                            CHAR(36)       NOT NULL PRIMARY KEY,
    invoice_id                    CHAR(36)       NOT NULL,
    dunning_number                VARCHAR(50)    NOT NULL,
    dunning_level_id              CHAR(36)       NULL,
    level_name_snapshot           VARCHAR(255)   NOT NULL,
    days_after_due_snapshot       INT            NOT NULL,
    fee_type_snapshot             ENUM('none', 'fixed', 'percent') NOT NULL,
    fee_value_snapshot            DECIMAL(10, 2) NULL,
    fee_amount_snapshot           DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    invoice_gross_total_snapshot  DECIMAL(10, 2) NOT NULL,
    created_at                    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at                       DATETIME       NULL,
    created_by                    ENUM('manual', 'automatic') NOT NULL DEFAULT 'manual',
    UNIQUE KEY uq_dunning_notices_dunning_number (dunning_number),
    CONSTRAINT fk_dunning_notices_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_dunning_notices_dunning_level
        FOREIGN KEY (dunning_level_id) REFERENCES dunning_levels (id) ON DELETE SET NULL,
    INDEX idx_dunning_notices_invoice (invoice_id),
    INDEX idx_dunning_notices_dunning_level (dunning_level_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Fahrzeug-Stammdaten (siehe database/migrations/073_add_vehicles.sql und
-- 093_vehicle_load_area_dimensions.sql für Details/Kommentare). Je Fahrzeug
-- wird die Zuladung (payload_kg) sowie Länge/Breite/Höhe der Ladefläche in
-- cm gespeichert; load_area_height_cm ist NULL, wenn load_area_open_top = 1
-- (offene Ladefläche/Pritsche ohne Aufbau, keine Höhenbegrenzung).
-- Anhängekupplung/Anhängelast sind nur bei category = 'auto' relevant, das
-- Leergewicht nur bei category = 'anhaenger' (die Anwendung erzwingt das,
-- nicht die Datenbank, analog zu is19Inch/heightUnits bei Article).
-- ---------------------------------------------------------------------
CREATE TABLE vehicles (
    id                      CHAR(36)       NOT NULL PRIMARY KEY,
    category                ENUM('auto', 'anhaenger') NOT NULL,
    name                    VARCHAR(255)   NOT NULL,
    license_plate           VARCHAR(20)    NULL,
    payload_kg              DECIMAL(8, 2)  NULL,
    load_area_length_cm     DECIMAL(6, 1)  NULL,
    load_area_width_cm      DECIMAL(6, 1)  NULL,
    load_area_height_cm     DECIMAL(6, 1)  NULL,
    load_area_open_top      TINYINT(1)     NOT NULL DEFAULT 0,
    tow_hitch_available     TINYINT(1)     NULL,
    tow_capacity_kg         DECIMAL(8, 2)  NULL,
    empty_weight_kg         DECIMAL(8, 2)  NULL,
    notes                   TEXT           NULL,
    created_at              DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vehicles_category (category),
    INDEX idx_vehicles_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Beladungsliste: ordnet eine konkret ausgegebene Inventarnummer
-- (rental_issued_items) einem Fahrzeug zu. UNIQUE auf
-- rental_issued_item_id, da ein Posten immer nur in genau einem Fahrzeug
-- verladen sein kann.
-- ---------------------------------------------------------------------
CREATE TABLE rental_vehicle_loads (
    id                      CHAR(36) NOT NULL PRIMARY KEY,
    rental_id               CHAR(36) NOT NULL,
    rental_issued_item_id   CHAR(36) NOT NULL,
    vehicle_id              CHAR(36) NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_vehicle_loads_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_vehicle_loads_issued_item
        FOREIGN KEY (rental_issued_item_id) REFERENCES rental_issued_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_vehicle_loads_vehicle
        FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_vehicle_loads_issued_item (rental_issued_item_id),
    INDEX idx_rental_vehicle_loads_rental (rental_id),
    INDEX idx_rental_vehicle_loads_vehicle (vehicle_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Zugfahrzeug-Zuordnung je Veranstaltung: welches Auto zieht welchen
-- Anhänger, für die Prüfung "Leergewicht Anhänger + Beladung Anhänger <=
-- Anhängelast Auto" (siehe VehicleLoadCalculator). UNIQUE auf
-- (rental_id, towed_vehicle_id), da ein Anhänger je Veranstaltung nur von
-- einem Zugfahrzeug gezogen werden kann.
-- ---------------------------------------------------------------------
CREATE TABLE rental_vehicle_tows (
    id                  CHAR(36) NOT NULL PRIMARY KEY,
    rental_id           CHAR(36) NOT NULL,
    towed_vehicle_id    CHAR(36) NOT NULL,
    towing_vehicle_id   CHAR(36) NOT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_vehicle_tows_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_vehicle_tows_towed
        FOREIGN KEY (towed_vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_rental_vehicle_tows_towing
        FOREIGN KEY (towing_vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_vehicle_tows_rental_towed (rental_id, towed_vehicle_id),
    INDEX idx_rental_vehicle_tows_rental (rental_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Gutscheine (siehe database/migrations/074_add_vouchers.sql für
-- ausführliche Kommentare).
-- ---------------------------------------------------------------------
CREATE TABLE vouchers (
    id              CHAR(36)        NOT NULL PRIMARY KEY,
    code            VARCHAR(40)     NOT NULL,
    discount_type   ENUM('prozent', 'betrag') NOT NULL,
    discount_value  DECIMAL(10, 2)  NOT NULL,
    valid_from      DATE            NOT NULL,
    valid_until     DATE            NOT NULL,
    notes           TEXT            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vouchers_code (code),
    INDEX idx_vouchers_valid_until (valid_until)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Siehe Migration 099: optionale Einschränkung eines Gutscheins auf eine
-- Auswahl von Kunden (kein Eintrag = für alle Kunden gültig).
CREATE TABLE voucher_customers (
    voucher_id  CHAR(36) NOT NULL,
    customer_id CHAR(36) NOT NULL,
    PRIMARY KEY (voucher_id, customer_id),
    CONSTRAINT fk_voucher_customers_voucher
        FOREIGN KEY (voucher_id) REFERENCES vouchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_voucher_customers_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    INDEX idx_voucher_customers_customer (customer_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE voucher_redemptions (
    id              CHAR(36)   NOT NULL PRIMARY KEY,
    voucher_id      CHAR(36)   NOT NULL,
    rental_id       CHAR(36)   NOT NULL,
    redeemed_at     DATETIME   NOT NULL,
    CONSTRAINT fk_voucher_redemptions_voucher
        FOREIGN KEY (voucher_id) REFERENCES vouchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_voucher_redemptions_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    UNIQUE KEY uq_voucher_redemptions_voucher (voucher_id),
    UNIQUE KEY uq_voucher_redemptions_rental (rental_id),
    INDEX idx_voucher_redemptions_rental (rental_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE voucher_settings (
    id                      TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    default_validity_days  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO voucher_settings (id, default_validity_days) VALUES (1, 30);

-- ---------------------------------------------------------------------
-- Impersonations-Protokoll (siehe database/migrations/075_add_impersonations.sql
-- für ausführliche Kommentare).
-- ---------------------------------------------------------------------
CREATE TABLE impersonations (
    id              CHAR(36)   NOT NULL PRIMARY KEY,
    admin_user_id   CHAR(36)   NOT NULL,
    target_user_id  CHAR(36)   NOT NULL,
    started_at      DATETIME   NOT NULL,
    ended_at        DATETIME   NULL,
    CONSTRAINT fk_impersonations_admin
        FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_impersonations_target
        FOREIGN KEY (target_user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_impersonations_admin (admin_user_id),
    INDEX idx_impersonations_target (target_user_id),
    INDEX idx_impersonations_started_at (started_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Direktverkauf von Artikeln: Bestellverwaltung + reiner Verkaufsbestand
-- (siehe database/migrations/078_add_purchase_orders_and_sale_stock.sql für
-- ausführliche Kommentare, App\Domain\Purchase, App\Domain\Stock).
-- ---------------------------------------------------------------------
CREATE TABLE purchase_orders (
    id            CHAR(36)     NOT NULL PRIMARY KEY,
    supplier_id   CHAR(36)     NOT NULL,
    status        VARCHAR(20)  NOT NULL DEFAULT 'draft',
    ordered_at    DATETIME     NULL,
    note          TEXT         NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_purchase_orders_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
    INDEX idx_purchase_orders_supplier (supplier_id),
    INDEX idx_purchase_orders_status (status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE purchase_order_items (
    id                 CHAR(36)     NOT NULL PRIMARY KEY,
    purchase_order_id  CHAR(36)     NOT NULL,
    article_id         CHAR(36)     NOT NULL,
    target_type        VARCHAR(20)  NOT NULL,
    ordered_quantity   INT UNSIGNED NOT NULL,
    unit_price         DECIMAL(10, 2) NOT NULL,
    received_quantity  INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_purchase_order_items_order
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_purchase_order_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_purchase_order_items_order (purchase_order_id),
    INDEX idx_purchase_order_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE sale_stock_lots (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    article_id              CHAR(36)     NOT NULL,
    purchase_order_item_id  CHAR(36)     NULL,
    unit_purchase_price     DECIMAL(10, 2) NOT NULL,
    quantity_received       INT UNSIGNED NOT NULL,
    quantity_remaining      INT UNSIGNED NOT NULL,
    received_at             DATETIME     NOT NULL,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sale_stock_lots_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sale_stock_lots_purchase_order_item
        FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items (id) ON DELETE SET NULL,
    INDEX idx_sale_stock_lots_article_received (article_id, received_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE sale_stock_consumptions (
    id                   CHAR(36)     NOT NULL PRIMARY KEY,
    sale_id              CHAR(36)     NOT NULL,
    stock_lot_id         CHAR(36)     NOT NULL,
    quantity             INT UNSIGNED NOT NULL,
    unit_purchase_price  DECIMAL(10, 2) NOT NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sale_stock_consumptions_sale
        FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_stock_consumptions_stock_lot
        FOREIGN KEY (stock_lot_id) REFERENCES sale_stock_lots (id) ON DELETE RESTRICT,
    INDEX idx_sale_stock_consumptions_sale (sale_id),
    INDEX idx_sale_stock_consumptions_stock_lot (stock_lot_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Verkaufspositionen eines Angebots — strukturell analog zu offer_items/
-- offer_labor_items (eigene Tabelle statt Diskriminator-Feld).
CREATE TABLE offer_sale_items (
    id                   CHAR(36)     NOT NULL PRIMARY KEY,
    offer_id             CHAR(36)     NOT NULL,
    article_id           CHAR(36)     NOT NULL,
    inventory_number_id  CHAR(36)     NULL,
    quantity             INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price           DECIMAL(10, 2) NOT NULL,
    line_note            VARCHAR(255) NULL,
    fulfilled_sale_id    CHAR(36)     NULL,
    CONSTRAINT fk_offer_sale_items_offer
        FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    CONSTRAINT fk_offer_sale_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_offer_sale_items_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_offer_sale_items_fulfilled_sale
        FOREIGN KEY (fulfilled_sale_id) REFERENCES sales (id) ON DELETE SET NULL,
    INDEX idx_offer_sale_items_offer (offer_id),
    INDEX idx_offer_sale_items_article (article_id),
    INDEX idx_offer_sale_items_inventory_number (inventory_number_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =======================================================================
-- Ergänzungen, die bislang in diesem schema.sql fehlten, obwohl die
-- zugehörigen Migrationen bereits lange existieren (schema.sql wurde nicht
-- zuverlässig synchron zu database/migrations/ gepflegt, siehe Hinweis in
-- App\Infrastructure\Migrations\MigrationRunner). Nachgetragen, damit ein
-- Installationslauf auf einer wirklich leeren Datenbank (öffentlicher
-- Web-Installer, public/install.php) direkt aus diesem schema.sql heraus
-- den vollständigen, aktuellen Stand erzeugt. Inhaltlich 1:1 aus den
-- jeweiligen Migrationsdateien übernommen (siehe Dateinamen in den
-- Kommentaren), keine eigenständige Schema-Neuerfindung.
-- =======================================================================

-- (Die früher hier angelegten Tabellen invoice_sequences [031] und
-- dunning_sequences [052] entfallen: Migration 084 hat sie durch
-- document_number_sequences ersetzt und gelöscht — eine über Migrationen
-- gewachsene Installation besitzt sie nicht mehr.)

-- --- 065_add_carddav_credentials.sql ------------------------------------
CREATE TABLE carddav_credentials (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    user_id           CHAR(36)     NOT NULL,
    name              VARCHAR(255) NOT NULL,
    token_hash        CHAR(64)     NOT NULL,
    token_last_four   CHAR(4)      NOT NULL,
    last_used_at      DATETIME     NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_carddav_credentials_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uniq_carddav_credentials_token_hash UNIQUE (token_hash),
    INDEX idx_carddav_credentials_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 066_add_pdf_design_settings.sql ------------------------------------
CREATE TABLE pdf_design_settings (
    id           TINYINT              NOT NULL PRIMARY KEY DEFAULT 1,
    accent_r     TINYINT UNSIGNED     NOT NULL DEFAULT 0,
    accent_g     TINYINT UNSIGNED     NOT NULL DEFAULT 0,
    accent_b     TINYINT UNSIGNED     NOT NULL DEFAULT 0,
    logo_position ENUM('left', 'right') NOT NULL DEFAULT 'right',
    currency_display ENUM('code', 'symbol') NOT NULL DEFAULT 'code', -- Beträge mit "EUR" oder "€" (Migration 110)
    updated_at   DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_pdf_design_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO pdf_design_settings (id, accent_r, accent_g, accent_b, logo_position) VALUES (1, 0, 0, 0, 'right');

-- --- 067_add_rental_reminders.sql ---------------------------------------
CREATE TABLE rental_reminder_settings (
    id                 TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    pickup_lead_hours  INT UNSIGNED NOT NULL DEFAULT 24,
    return_lead_hours  INT UNSIGNED NOT NULL DEFAULT 24,
    CONSTRAINT chk_rental_reminder_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO rental_reminder_settings (id, pickup_lead_hours, return_lead_hours) VALUES (1, 24, 24);

-- type-ENUM bereits direkt in der finalen Form (inkl. 'customer_checklist',
-- siehe 071_add_rental_customer_checklist.sql) angelegt, statt wie die
-- Migrationshistorie erst mit ('pickup','return') zu erstellen und danach
-- separat per MODIFY COLUMN zu erweitern — das Endergebnis ist identisch.
CREATE TABLE rental_reminders (
    id         CHAR(36)                                        NOT NULL PRIMARY KEY,
    rental_id  CHAR(36)                                        NOT NULL,
    type       ENUM('pickup', 'return', 'customer_checklist')  NOT NULL,
    sent_at    DATETIME                                        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_reminders_rental_type (rental_id, type),
    CONSTRAINT fk_rental_reminders_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    INDEX idx_rental_reminders_rental (rental_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 068_add_overage_settings.sql ---------------------------------------
CREATE TABLE overage_settings (
    id               TINYINT        NOT NULL PRIMARY KEY DEFAULT 1,
    flat_fee_enabled TINYINT(1)     NOT NULL DEFAULT 0,
    flat_fee_amount  DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    CONSTRAINT chk_overage_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO overage_settings (id, flat_fee_enabled, flat_fee_amount) VALUES (1, 0, 0.00);

-- --- 071_add_rental_customer_checklist.sql ------------------------------
CREATE TABLE rental_customer_checklist_tokens (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id  CHAR(36)     NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_customer_checklist_tokens_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT uq_rental_customer_checklist_tokens_rental UNIQUE (rental_id),
    CONSTRAINT uq_rental_customer_checklist_tokens_token UNIQUE (token)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE rental_issued_items
    ADD COLUMN confirmed_by_customer_at DATETIME NULL AFTER function_test_passed;

ALTER TABLE rental_reminder_settings
    ADD COLUMN checklist_lead_hours INT UNSIGNED NOT NULL DEFAULT 24,
    ADD COLUMN checklist_auto_send_enabled TINYINT(1) NOT NULL DEFAULT 1;

INSERT INTO email_templates (template_key, subject, body_html) VALUES
('rental_customer_checklist', 'Vollständigkeits-Checkliste für {{event_name}}',
 '<p>Guten Tag {{customer_name}},</p>\n<p>bitte prüfen Sie vor dem Abbau über folgenden Link, ob alle für Ihre Veranstaltung ausgegebenen Artikel vollständig vorhanden sind:</p>\n<p><a href="{{link}}">{{link}}</a></p>\n<p>Veranstaltung: {{event_name}}<br>Rückgabetermin: {{return_at}}</p>\n<p>Mit freundlichen Grüßen</p>');

-- --- 086_add_invoice_due_reminders.sql -----------------------------------
CREATE TABLE invoice_due_reminder_settings (
    id         TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    enabled    TINYINT(1)   NOT NULL DEFAULT 0,
    lead_days  INT UNSIGNED NOT NULL DEFAULT 3,
    CONSTRAINT chk_invoice_due_reminder_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO invoice_due_reminder_settings (id, enabled, lead_days) VALUES (1, 0, 3);

CREATE TABLE invoice_due_reminders (
    id         CHAR(36)  NOT NULL PRIMARY KEY,
    invoice_id CHAR(36)  NOT NULL,
    sent_at    DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_due_reminders_invoice (invoice_id),
    CONSTRAINT fk_invoice_due_reminders_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 087_add_article_pre_issue_checks.sql --------------------------------
CREATE TABLE article_pre_issue_checks (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    article_id   CHAR(36)     NOT NULL,
    description  VARCHAR(255) NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_pre_issue_checks_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_article_pre_issue_checks_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE rental_items
    ADD COLUMN pre_issue_checks_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER accessory_checked;

-- --- 088_add_ai_ad_images.sql --------------------------------------------
CREATE TABLE ai_ad_image_settings (
    id              TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    openai_api_key  VARCHAR(255) NULL,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_ai_ad_image_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO ai_ad_image_settings (id) VALUES (1);

CREATE TABLE article_ad_images (
    id                       CHAR(36)     NOT NULL PRIMARY KEY,
    article_id               CHAR(36)     NOT NULL,
    file_path                VARCHAR(500) NOT NULL,
    format                   VARCHAR(20)  NOT NULL,
    prompt                   TEXT         NOT NULL,
    source_article_image_id  CHAR(36)     NULL,
    created_at                DATETIME     NOT NULL,
    CONSTRAINT fk_article_ad_images_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_article_ad_images_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 113_article_sets.sql ------------------------------------------------
CREATE TABLE article_sets (
    id              CHAR(36)      NOT NULL PRIMARY KEY,
    name            VARCHAR(255)  NOT NULL,
    description     TEXT          NULL,
    price           DECIMAL(10,2) NOT NULL,
    deployment_days INT UNSIGNED  NOT NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_set_items (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    set_id     CHAR(36)     NOT NULL,
    article_id CHAR(36)     NOT NULL, -- Artikel oder Bundle
    quantity   INT UNSIGNED NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    CONSTRAINT fk_article_set_items_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_set_items_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_article_set_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_set_rules (
    id             CHAR(36)    NOT NULL PRIMARY KEY,
    set_id         CHAR(36)    NOT NULL,
    type           ENUM('weekends', 'weekdays', 'yearly_period', 'date_range') NOT NULL,
    from_month_day CHAR(5)     NULL, -- "MM-DD", nur yearly_period
    to_month_day   CHAR(5)     NULL,
    from_date      DATE        NULL, -- nur date_range
    to_date        DATE        NULL,
    sort_order     INT         NOT NULL DEFAULT 0,
    CONSTRAINT fk_article_set_rules_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_set_upgrades (
    id         CHAR(36)      NOT NULL PRIMARY KEY,
    set_id     CHAR(36)      NOT NULL,
    name       VARCHAR(255)  NOT NULL,
    price      DECIMAL(10,2) NOT NULL,
    sort_order INT           NOT NULL DEFAULT 0,
    CONSTRAINT fk_article_set_upgrades_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_set_upgrade_items (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    upgrade_id CHAR(36)     NOT NULL,
    article_id CHAR(36)     NOT NULL,
    quantity   INT UNSIGNED NOT NULL,
    CONSTRAINT fk_article_set_upgrade_items_upgrade FOREIGN KEY (upgrade_id) REFERENCES article_set_upgrades (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_set_upgrade_items_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_article_set_upgrade_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Set- und Upgrade-Positionen in Dokumenten: Freitext-Zeile mit Verweis.
ALTER TABLE offer_free_text_items
    ADD COLUMN set_id CHAR(36) NULL,
    ADD COLUMN set_upgrade_id CHAR(36) NULL,
    ADD CONSTRAINT fk_offer_free_text_items_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_offer_free_text_items_upgrade FOREIGN KEY (set_upgrade_id) REFERENCES article_set_upgrades (id) ON DELETE SET NULL;
ALTER TABLE order_confirmation_free_text_items
    ADD COLUMN set_id CHAR(36) NULL,
    ADD COLUMN set_upgrade_id CHAR(36) NULL,
    ADD CONSTRAINT fk_oc_free_text_items_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_oc_free_text_items_upgrade FOREIGN KEY (set_upgrade_id) REFERENCES article_set_upgrades (id) ON DELETE SET NULL;
ALTER TABLE invoice_free_text_items
    ADD COLUMN set_id CHAR(36) NULL,
    ADD COLUMN set_upgrade_id CHAR(36) NULL,
    ADD CONSTRAINT fk_invoice_free_text_items_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_invoice_free_text_items_upgrade FOREIGN KEY (set_upgrade_id) REFERENCES article_set_upgrades (id) ON DELETE SET NULL;

-- In Veranstaltungen gebuchte Sets und die dafür eingeplanten Artikel
-- (Bundle-Bestandteile bereits aufgelöst, inkl. Alternativen).
CREATE TABLE rental_set_bookings (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id   CHAR(36)     NOT NULL,
    set_id      CHAR(36)     NOT NULL,
    quantity    INT UNSIGNED NOT NULL,
    upgrade_ids TEXT         NULL, -- kommagetrennte article_set_upgrades.id
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_set_bookings_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_set_bookings_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_set_allocations (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    booking_id CHAR(36)     NOT NULL,
    article_id CHAR(36)     NOT NULL,
    quantity   INT UNSIGNED NOT NULL,
    CONSTRAINT fk_rental_set_allocations_booking FOREIGN KEY (booking_id) REFERENCES rental_set_bookings (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_set_allocations_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_set_allocations (booking_id, article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Anrede bei Kunden und Ansprechpartnern ('herr', 'frau', 'divers'; NULL = keine).
ALTER TABLE customers ADD COLUMN salutation VARCHAR(10) NULL AFTER type;
ALTER TABLE customer_contacts ADD COLUMN salutation VARCHAR(10) NULL AFTER customer_id;

-- Starttext je Dokumenttyp ('offer', 'order_confirmation', 'invoice').
CREATE TABLE document_intro_texts (
    document_type VARCHAR(30) NOT NULL PRIMARY KEY,
    text          TEXT        NULL,
    updated_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 114_case_images_notice.sql ---
-- Migration 114: Bilder vom befüllten Case und Hinweistext je Case-Exemplar
-- (Nutzerwunsch). Beides erscheint auf der Stückliste (PDF) des Exemplars.

ALTER TABLE case_inventory_numbers
    ADD COLUMN notice_html TEXT NULL AFTER acquisition_date; -- bereinigtes HTML, siehe HtmlSanitizer

CREATE TABLE case_inventory_number_images (
    id                          CHAR(36)     NOT NULL PRIMARY KEY,
    case_inventory_number_id    CHAR(36)     NOT NULL,
    file_path                   VARCHAR(255) NOT NULL, -- relativ zu storage/cases
    position                    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_case_inventory_number_images_inventory_number
        FOREIGN KEY (case_inventory_number_id) REFERENCES case_inventory_numbers (id) ON DELETE CASCADE,
    INDEX idx_case_inventory_number_images_inventory_number (case_inventory_number_id, position)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 115_racks.sql ---
-- Migration 115: Racks (Nutzerwunsch). Jedes Rack ist ein einzelnes
-- physisches Teil mit eigener Inventarnummer. Bestückt wird es mit konkreten
-- Geräte-Exemplaren (19-Zoll-Artikel) an einer HE-Position. Optional ist das
-- Rack einem Geräte-Exemplar fest zugeordnet und geht dann bei dessen
-- Ausgabe in einer Veranstaltung automatisch mit.

CREATE TABLE racks (
    id                          CHAR(36)      NOT NULL PRIMARY KEY,
    name                        VARCHAR(255)  NOT NULL,
    inventory_number            VARCHAR(100)  NOT NULL,
    manufacturer_id             CHAR(36)      NULL,
    height_units                INT UNSIGNED  NOT NULL,
    install_depth_cm            DECIMAL(6,1)  NULL,
    rail_type                   VARCHAR(20)   NOT NULL DEFAULT 'front', -- front | front_back
    width                       VARCHAR(10)   NOT NULL DEFAULT '19',    -- 19 | 9.5 (Zoll)
    color                       VARCHAR(100)  NULL,
    has_shock_mount             TINYINT(1)    NOT NULL DEFAULT 0,
    has_wheels                  TINYINT(1)    NOT NULL DEFAULT 0,
    fixed_inventory_number_id   CHAR(36)      NULL,
    description                 TEXT          NULL,
    created_at                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_racks_manufacturer
        FOREIGN KEY (manufacturer_id) REFERENCES manufacturers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_racks_fixed_inventory_number
        FOREIGN KEY (fixed_inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE SET NULL,
    UNIQUE KEY uq_racks_inventory_number (inventory_number),
    UNIQUE KEY uq_racks_fixed_inventory_number (fixed_inventory_number_id),
    INDEX idx_racks_manufacturer (manufacturer_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rack_slots (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    rack_id                 CHAR(36)     NOT NULL,
    inventory_number_id     CHAR(36)     NOT NULL,
    start_unit              INT UNSIGNED NOT NULL, -- oberste belegte HE, von oben gezählt ab 1
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rack_slots_rack
        FOREIGN KEY (rack_id) REFERENCES racks (id) ON DELETE CASCADE,
    CONSTRAINT fk_rack_slots_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE CASCADE,
    UNIQUE KEY uq_rack_slots_inventory_number (inventory_number_id), -- ein Gerät steckt in höchstens einem Rack
    INDEX idx_rack_slots_rack (rack_id, start_unit)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 116_pdf_fonts.sql ---
-- Migration 116: Schriftarten für PDF-Dokumente und -Listen (Nutzerwunsch).
-- Schriften werden einmalig von Google Fonts geladen (oder als TTF
-- hochgeladen), unter storage/uploads/fonts/<id>/ abgelegt und je Bereich
-- (Text, Überschriften, Tabellen, Fußzeile) zugeordnet. In die PDFs wird
-- nur der benötigte Zeichenvorrat eingebettet.

CREATE TABLE pdf_fonts (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    family              VARCHAR(150) NOT NULL,
    source              VARCHAR(20)  NOT NULL DEFAULT 'google', -- google | upload
    regular_file        VARCHAR(255) NOT NULL,
    bold_file           VARCHAR(255) NULL,
    italic_file         VARCHAR(255) NULL,
    bold_italic_file    VARCHAR(255) NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pdf_fonts_family (family)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE pdf_font_assignments (
    role        VARCHAR(20) NOT NULL PRIMARY KEY, -- body | heading | table | footer
    font_id     CHAR(36)    NULL,
    updated_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pdf_font_assignments_font
        FOREIGN KEY (font_id) REFERENCES pdf_fonts (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 117_power_distributors.sql ---
-- Migration 117: Stromverteiler als Sonderform von Artikeln (Nutzerwunsch).
-- Zuleitung, Schutzorgane (RCD/FI, LS, FI/LS-Kombination) und Abgänge mit
-- Zuordnung zum vorgeschalteten Schutzorgan. Ein LS kann hinter einem RCD
-- liegen (upstream_device_id), Abgänge ohne Schutzorgan gehen direkt von der
-- Zuleitung ab.

CREATE TABLE power_distributors (
    article_id      CHAR(36)    NOT NULL PRIMARY KEY,
    inlet_type      VARCHAR(30) NOT NULL,
    notes           TEXT        NULL,
    updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_power_distributors_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE power_distributor_devices (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    article_id              CHAR(36)     NOT NULL,
    label                   VARCHAR(50)  NOT NULL,
    device_type             VARCHAR(10)  NOT NULL, -- rcd | mcb | rcbo
    poles                   TINYINT UNSIGNED NULL,
    rcd_type                VARCHAR(5)   NULL,     -- AC | A | F | B | B+
    residual_current_ma     INT UNSIGNED NULL,     -- Auslösestrom (Bemessungsdifferenzstrom)
    rated_current_a         INT UNSIGNED NULL,     -- Sicherungsgröße (LS) bzw. Nennstrom (RCD)
    characteristic          VARCHAR(2)   NULL,     -- B | C | D | K | Z
    upstream_device_id      CHAR(36)     NULL,     -- RCD vor einem LS
    sort_order              INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_pd_devices_article
        FOREIGN KEY (article_id) REFERENCES power_distributors (article_id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_devices_upstream
        FOREIGN KEY (upstream_device_id) REFERENCES power_distributor_devices (id) ON DELETE SET NULL,
    INDEX idx_pd_devices_article (article_id, sort_order)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE power_distributor_outlets (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    article_id      CHAR(36)     NOT NULL,
    connector_type  VARCHAR(30)  NOT NULL,
    quantity        INT UNSIGNED NOT NULL DEFAULT 1,
    label           VARCHAR(50)  NULL,
    device_id       CHAR(36)     NULL, -- null = direkt von der Zuleitung
    sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_pd_outlets_article
        FOREIGN KEY (article_id) REFERENCES power_distributors (article_id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_outlets_device
        FOREIGN KEY (device_id) REFERENCES power_distributor_devices (id) ON DELETE SET NULL,
    INDEX idx_pd_outlets_article (article_id, sort_order)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 118_power_distributor_category.sql ---
-- Migration 118: Stromverteiler haben immer die Artikelkategorie
-- "Stromverteiler" (Nutzerwunsch). Die Kategorie wird über
-- categories.is_power_distributor erkannt (nicht über den Namen, damit sie
-- umbenannt werden kann). Eine vorhandene Hauptkategorie "Stromverteiler"
-- wird übernommen, sonst angelegt. Bereits erfasste Stromverteiler werden
-- dieser Kategorie zugeordnet.

ALTER TABLE categories
    ADD COLUMN is_power_distributor TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_id;

UPDATE categories SET is_power_distributor = 1
WHERE name = 'Stromverteiler'
ORDER BY (parent_id IS NOT NULL), created_at
LIMIT 1;

INSERT INTO categories (id, name, parent_id, is_power_distributor)
SELECT UUID(), 'Stromverteiler', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE is_power_distributor = 1);

UPDATE articles
SET category_id = (SELECT id FROM categories WHERE is_power_distributor = 1 LIMIT 1)
WHERE id IN (SELECT article_id FROM power_distributors);

-- --- 119_generic_manufacturer.sql ---
-- Migration 119: Hersteller "Generisch" (Kürzel GEN) für selbst gebaute
-- Artikel wie Kabel (Nutzerwunsch). Ein Artikel braucht weiterhin einen
-- Hersteller; "Generisch" wird bewusst von Hand ausgewählt. Erkennung über
-- manufacturers.is_generic: der Name erscheint dann nicht vor der
-- Artikelbezeichnung, und der Hersteller kann nicht gelöscht werden.

ALTER TABLE manufacturers
    ADD COLUMN is_generic TINYINT(1) NOT NULL DEFAULT 0 AFTER prefix;

-- Vorhandenen Hersteller "Generisch" übernehmen (Kürzel GEN setzen, falls frei) …
UPDATE manufacturers SET is_generic = 1 WHERE name = 'Generisch' LIMIT 1;
UPDATE manufacturers m
SET m.prefix = 'GEN'
WHERE m.is_generic = 1
  AND (m.prefix IS NULL OR m.prefix = '')
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM manufacturers WHERE prefix = 'GEN') AS taken);

-- … sonst anlegen (Kürzel GEN, falls kein anderer Hersteller es belegt).
INSERT INTO manufacturers (id, name, prefix, is_generic)
SELECT UUID(), 'Generisch',
       IF(EXISTS (SELECT 1 FROM manufacturers WHERE prefix = 'GEN'), NULL, 'GEN'),
       1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM manufacturers WHERE is_generic = 1);

-- --- 120_case_rack_numbering.sql ---
-- Migration 120: Inventarnummern für Cases und Racks automatisch und
-- fortlaufend (Nutzerwunsch): CASE-0001, RACK-0001 … über das bestehende
-- Nummerierungssystem (Einstellungen › Nummerierung, dort anpassbar).
-- Vorhandene Nummern bleiben unverändert.

INSERT IGNORE INTO document_numbering_settings (document_type, scheme, prefix, `separator`, digit_count) VALUES
    ('case_item', 'simple', 'CASE-', '-', 4),
    ('rack', 'simple', 'RACK-', '-', 4);

INSERT IGNORE INTO document_number_sequences (document_type, sequence_year, next_number) VALUES
    ('case_item', 0, 1),
    ('rack', 0, 1);

-- Migration 121: Rechnungskorrekturen (Nutzerwunsch). Eine versendete oder
-- bezahlte Rechnung bleibt unverändert; Änderungen erfolgen über ein eigenes
-- Dokument mit Bezug auf die Rechnung:
--   - cancellation: Stornorechnung, alle Positionen negativ (Vollstorno)
--   - partial:      Rechnungskorrektur, nur die Differenz (Teilkorrektur)
-- Eigener Nummernkreis RK-0001 (Einstellungen › Nummerierung).

CREATE TABLE invoice_corrections (
    id                   CHAR(36)      NOT NULL PRIMARY KEY,
    invoice_id           CHAR(36)      NOT NULL,
    correction_type      VARCHAR(20)   NOT NULL,                 -- cancellation | partial
    status               VARCHAR(10)   NOT NULL DEFAULT 'draft', -- draft | issued
    correction_number    VARCHAR(40)   NULL,
    correction_date      DATE          NULL,
    reason               TEXT          NOT NULL,
    note                 TEXT          NULL,
    is_small_business    TINYINT(1)    NOT NULL DEFAULT 0,       -- Snapshot aus der Rechnung
    vat_rate_percent     DECIMAL(5,2)  NOT NULL DEFAULT 19.00,   -- Snapshot aus der Rechnung
    tax_amount_fixed     DECIMAL(12,2) NULL,                     -- Stornorechnung: exakt umgekehrte USt der Rechnung (+ Korrekturen)
    requires_settlement  TINYINT(1)    NOT NULL DEFAULT 0,       -- Rechnung war beim Ausstellen schon bezahlt
    settled_at           DATE          NULL,                     -- Erstattung/Nachzahlung erfolgt
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_corrections_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_invoice_corrections_number (correction_number),
    INDEX idx_invoice_corrections_invoice (invoice_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE invoice_correction_lines (
    id              CHAR(36)       NOT NULL PRIMARY KEY,
    correction_id   CHAR(36)       NOT NULL,
    sort_order      INT            NOT NULL DEFAULT 0,
    description     VARCHAR(500)   NOT NULL,
    quantity        DECIMAL(10,2)  NOT NULL DEFAULT 1.00,
    unit_price      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,   -- netto, negativ = Gutschrift
    CONSTRAINT fk_invoice_correction_lines_correction
        FOREIGN KEY (correction_id) REFERENCES invoice_corrections (id) ON DELETE CASCADE,
    INDEX idx_invoice_correction_lines_correction (correction_id, sort_order)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT IGNORE INTO document_numbering_settings (document_type, scheme, prefix, `separator`, digit_count) VALUES
    ('invoice_correction', 'simple', 'RK-', '-', 4);
INSERT IGNORE INTO document_number_sequences (document_type, sequence_year, next_number) VALUES
    ('invoice_correction', 0, 1);

-- Migration 122: Rechnungskorrekturen per E-Mail versenden (Nutzerwunsch).
-- Merkt sich, wann und an wen eine Korrektur zuletzt verschickt wurde, und
-- legt die bearbeitbare E-Mail-Vorlage an (Einstellungen › E-Mail-Vorlagen).

ALTER TABLE invoice_corrections
    ADD COLUMN sent_at DATETIME     NULL AFTER settled_at,
    ADD COLUMN sent_to VARCHAR(255) NULL AFTER sent_at;

INSERT IGNORE INTO email_templates (template_key, subject, body_html) VALUES
('invoice_correction', '{{document_title}} {{correction_number}} zu Rechnung {{invoice_number}}',
 '<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie unsere {{document_title}} {{correction_number}} zur Rechnung {{invoice_number}} vom {{invoice_date}} (inkl. E-Rechnung als XML zur elektronischen Weiterverarbeitung).</p>\n<p>Grund: {{reason}}<br>Betrag: {{amount}}</p>\n<p>{{payment_line}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>');

-- Migration 123: Rechnungskorrekturen/Stornorechnungen automatisch ablegen
-- (Nutzerwunsch). Erweitert den ENUM document_type um 'invoice_correction',
-- damit ausgestellte Korrekturen über die Speicherziel-Zuordnungen
-- (Einstellungen › Dokumentablage) auf ein Ziellaufwerk gelegt und im
-- Upload-Protokoll erfasst werden – siehe IssueInvoiceCorrectionHandler.

ALTER TABLE document_storage_assignments
    MODIFY document_type ENUM('invoice', 'offer', 'packing_list', 'receipt', 'dunning_notice', 'invoice_correction') NOT NULL;

ALTER TABLE document_storage_upload_log
    MODIFY document_type ENUM('invoice', 'offer', 'packing_list', 'receipt', 'dunning_notice', 'invoice_correction') NOT NULL;

-- Migration 124 (Nutzerwunsch):
-- 1. Racks werden zu Rack-Typen mit einzelnen Exemplaren (wie Cases). Jedes
--    Exemplar hat eine Rack-Nummer (RACK-0001 …, Nummernkreis aus Migration
--    120), eine Inventurnummer wie bei Artikeln (Hersteller-Kürzel-Rack-
--    Kürzel-0001), eine eigene Bestückung und optional ein fest zugeordnetes
--    Gerät. Bestehende Racks werden zu je einem Typ mit einem Exemplar.
-- 2. Case-Exemplare bekommen zusätzlich zur Inventurnummer (wieder Hersteller-
--    Kürzel-Case-Kürzel-0001) eine Case-Nummer (CASE-0001 …).

-- --- Racks ---------------------------------------------------------------
ALTER TABLE racks ADD COLUMN prefix VARCHAR(10) NULL AFTER name;

-- Hersteller ist jetzt Pflicht (Grundlage der Inventurnummer); ohne
-- Hersteller angelegte Racks bekommen "Generisch" (Migration 119).
UPDATE racks SET manufacturer_id = (SELECT id FROM manufacturers WHERE is_generic = 1 ORDER BY name LIMIT 1)
WHERE manufacturer_id IS NULL;

CREATE TABLE rack_exemplars (
    id                          CHAR(36)      NOT NULL PRIMARY KEY,
    rack_id                     CHAR(36)      NOT NULL,
    rack_number                 VARCHAR(40)   NULL,
    inventory_number            VARCHAR(100)  NOT NULL,
    fixed_inventory_number_id   CHAR(36)      NULL,
    acquisition_date            DATE          NULL,
    created_at                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rack_exemplars_rack
        FOREIGN KEY (rack_id) REFERENCES racks (id) ON DELETE CASCADE,
    CONSTRAINT fk_rack_exemplars_fixed_inventory_number
        FOREIGN KEY (fixed_inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE SET NULL,
    UNIQUE KEY uq_rack_exemplars_rack_number (rack_number),
    UNIQUE KEY uq_rack_exemplars_inventory_number (inventory_number),
    UNIQUE KEY uq_rack_exemplars_fixed_inventory_number (fixed_inventory_number_id),
    INDEX idx_rack_exemplars_rack (rack_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Jedes bisherige Rack wird zu einem Exemplar; die bisherige Nummer bleibt
-- Inventurnummer, eine RACK-Nummer wird übernommen bzw. neu vergeben.
INSERT INTO rack_exemplars (id, rack_id, rack_number, inventory_number, fixed_inventory_number_id, created_at, updated_at)
SELECT UUID(), id, CASE WHEN inventory_number REGEXP '^RACK-[0-9]+$' THEN inventory_number ELSE NULL END,
       inventory_number, fixed_inventory_number_id, created_at, updated_at
FROM racks;

SET @rack_n := GREATEST(
    (SELECT COALESCE(MAX(CAST(SUBSTRING(rack_number, 6) AS UNSIGNED)), 0) FROM rack_exemplars WHERE rack_number IS NOT NULL),
    (SELECT COALESCE(MAX(next_number), 1) - 1 FROM document_number_sequences WHERE document_type = 'rack')
);
UPDATE rack_exemplars SET rack_number = CONCAT('RACK-', LPAD(@rack_n := @rack_n + 1, 4, '0'))
WHERE rack_number IS NULL ORDER BY created_at, id;
INSERT INTO document_number_sequences (document_type, sequence_year, next_number) VALUES ('rack', 0, @rack_n + 1)
ON DUPLICATE KEY UPDATE next_number = GREATEST(next_number, @rack_n + 1);

-- Bestückung hängt jetzt am Exemplar.
ALTER TABLE rack_slots ADD COLUMN rack_exemplar_id CHAR(36) NULL AFTER id;
UPDATE rack_slots s JOIN rack_exemplars e ON e.rack_id = s.rack_id SET s.rack_exemplar_id = e.id;
ALTER TABLE rack_slots DROP FOREIGN KEY fk_rack_slots_rack;
ALTER TABLE rack_slots DROP INDEX idx_rack_slots_rack;
ALTER TABLE rack_slots DROP COLUMN rack_id;
ALTER TABLE rack_slots MODIFY rack_exemplar_id CHAR(36) NOT NULL;
ALTER TABLE rack_slots
    ADD CONSTRAINT fk_rack_slots_exemplar FOREIGN KEY (rack_exemplar_id) REFERENCES rack_exemplars (id) ON DELETE CASCADE,
    ADD INDEX idx_rack_slots_exemplar (rack_exemplar_id, start_unit);

-- Nummer und feste Zuordnung liegen jetzt am Exemplar.
ALTER TABLE racks DROP FOREIGN KEY fk_racks_fixed_inventory_number;
ALTER TABLE racks DROP INDEX uq_racks_fixed_inventory_number;
ALTER TABLE racks DROP INDEX uq_racks_inventory_number;
ALTER TABLE racks DROP COLUMN fixed_inventory_number_id, DROP COLUMN inventory_number;

-- --- Cases ---------------------------------------------------------------
ALTER TABLE case_inventory_numbers
    ADD COLUMN case_number VARCHAR(40) NULL AFTER number,
    ADD UNIQUE KEY uq_case_inventory_numbers_case_number (case_number);

-- Seit Migration 120 vergebene CASE-Nummern werden zur Case-Nummer, alle
-- übrigen Exemplare bekommen fortlaufend eine neue.
UPDATE case_inventory_numbers SET case_number = number WHERE number REGEXP '^CASE-[0-9]+$';
SET @case_n := GREATEST(
    (SELECT COALESCE(MAX(CAST(SUBSTRING(case_number, 6) AS UNSIGNED)), 0) FROM case_inventory_numbers WHERE case_number IS NOT NULL),
    (SELECT COALESCE(MAX(next_number), 1) - 1 FROM document_number_sequences WHERE document_type = 'case_item')
);
UPDATE case_inventory_numbers SET case_number = CONCAT('CASE-', LPAD(@case_n := @case_n + 1, 4, '0'))
WHERE case_number IS NULL ORDER BY created_at, id;
INSERT INTO document_number_sequences (document_type, sequence_year, next_number) VALUES ('case_item', 0, @case_n + 1)
ON DUPLICATE KEY UPDATE next_number = GREATEST(next_number, @case_n + 1);

-- Bezeichnungen in Einstellungen › Nummerierung
INSERT IGNORE INTO document_numbering_settings (document_type, scheme, prefix, `separator`, digit_count) VALUES
    ('case_item', 'simple', 'CASE-', '-', 4), ('rack', 'simple', 'RACK-', '-', 4);

-- Migration 125: Leitungen als Spezialartikel (Nutzerwunsch, analog
-- Stromverteiler). In Einstellungen › Leitungen werden Leitungstypen (mit
-- Vorgabe zur Elektroprüfung) und Steckverbindertypen gepflegt sowie, welche
-- Steckverbinder an welchem Leitungstyp sinnvoll sind. Ein Leitungs-Artikel
-- besteht aus Leitungstyp, Länge und zwei Steckverbindern.
-- Leitungen haben immer die Kategorie "Leitungen" (categories.is_cable).

ALTER TABLE categories
    ADD COLUMN is_cable TINYINT(1) NOT NULL DEFAULT 0 AFTER is_power_distributor;

UPDATE categories SET is_cable = 1
WHERE name IN ('Leitungen', 'Kabel') AND is_power_distributor = 0
ORDER BY (name <> 'Leitungen'), (parent_id IS NOT NULL), created_at
LIMIT 1;

INSERT INTO categories (id, name, parent_id, is_cable)
SELECT UUID(), 'Leitungen', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE is_cable = 1);

CREATE TABLE cable_connector_types (
    id          CHAR(36)      NOT NULL PRIMARY KEY,
    name        VARCHAR(150)  NOT NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cable_connector_types_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE cable_types (
    id                              CHAR(36)      NOT NULL PRIMARY KEY,
    name                            VARCHAR(150)  NOT NULL,
    description                     VARCHAR(500)  NULL,
    requires_electrical_inspection  TINYINT(1)    NOT NULL DEFAULT 0,
    protection_class                VARCHAR(5)    NULL, -- Vorgabe für die Schutzklasse (I/II/III)
    sort_order                      INT           NOT NULL DEFAULT 0,
    created_at                      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cable_types_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Welche Steckverbinder an welchem Leitungstyp sinnvoll sind
CREATE TABLE cable_type_connectors (
    cable_type_id      CHAR(36) NOT NULL,
    connector_type_id  CHAR(36) NOT NULL,
    PRIMARY KEY (cable_type_id, connector_type_id),
    CONSTRAINT fk_cable_type_connectors_type
        FOREIGN KEY (cable_type_id) REFERENCES cable_types (id) ON DELETE CASCADE,
    CONSTRAINT fk_cable_type_connectors_connector
        FOREIGN KEY (connector_type_id) REFERENCES cable_connector_types (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_cables (
    article_id      CHAR(36)      NOT NULL PRIMARY KEY,
    cable_type_id   CHAR(36)      NOT NULL,
    length_m        DECIMAL(7,2)  NOT NULL,
    connector_a_id  CHAR(36)      NOT NULL,
    connector_b_id  CHAR(36)      NOT NULL,
    CONSTRAINT fk_article_cables_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_cables_type
        FOREIGN KEY (cable_type_id) REFERENCES cable_types (id) ON DELETE RESTRICT,
    CONSTRAINT fk_article_cables_connector_a
        FOREIGN KEY (connector_a_id) REFERENCES cable_connector_types (id) ON DELETE RESTRICT,
    CONSTRAINT fk_article_cables_connector_b
        FOREIGN KEY (connector_b_id) REFERENCES cable_connector_types (id) ON DELETE RESTRICT,
    INDEX idx_article_cables_type (cable_type_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Beispiel-Grundausstattung (kann in den Einstellungen geändert werden)
INSERT INTO cable_connector_types (id, name, sort_order) VALUES
    ('c0000000-0000-0000-0000-000000000001', 'Schuko-Stecker', 10),
    ('c0000000-0000-0000-0000-000000000002', 'Schuko-Kupplung', 11),
    ('c0000000-0000-0000-0000-000000000003', 'CEE 16 A Stecker blau', 20),
    ('c0000000-0000-0000-0000-000000000004', 'CEE 16 A Kupplung blau', 21),
    ('c0000000-0000-0000-0000-000000000005', 'CEE 16 A Stecker rot', 30),
    ('c0000000-0000-0000-0000-000000000006', 'CEE 16 A Kupplung rot', 31),
    ('c0000000-0000-0000-0000-000000000007', 'CEE 32 A Stecker rot', 40),
    ('c0000000-0000-0000-0000-000000000008', 'CEE 32 A Kupplung rot', 41),
    ('c0000000-0000-0000-0000-000000000009', 'XLR male', 50),
    ('c0000000-0000-0000-0000-000000000010', 'XLR female', 51);

INSERT INTO cable_types (id, name, requires_electrical_inspection, protection_class, sort_order) VALUES
    ('b0000000-0000-0000-0000-000000000001', 'H07RN-F 3G2,5', 1, 'I', 10),
    ('b0000000-0000-0000-0000-000000000002', 'H07RN-F 5G2,5', 1, 'I', 20),
    ('b0000000-0000-0000-0000-000000000003', 'H07RN-F 5G6', 1, 'I', 30),
    ('b0000000-0000-0000-0000-000000000004', 'Mikrofonkabel 2×0,22 mm²', 0, NULL, 40);

INSERT INTO cable_type_connectors (cable_type_id, connector_type_id) VALUES
    ('b0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001'),
    ('b0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000002'),
    ('b0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000003'),
    ('b0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000004'),
    ('b0000000-0000-0000-0000-000000000002', 'c0000000-0000-0000-0000-000000000005'),
    ('b0000000-0000-0000-0000-000000000002', 'c0000000-0000-0000-0000-000000000006'),
    ('b0000000-0000-0000-0000-000000000003', 'c0000000-0000-0000-0000-000000000007'),
    ('b0000000-0000-0000-0000-000000000003', 'c0000000-0000-0000-0000-000000000008'),
    ('b0000000-0000-0000-0000-000000000004', 'c0000000-0000-0000-0000-000000000009'),
    ('b0000000-0000-0000-0000-000000000004', 'c0000000-0000-0000-0000-000000000010');

-- --- 091_add_pdf_layout_elements.sql ---
-- Visueller PDF-Vorlagen-Editor (siehe App\Domain\Settings\PdfLayout,
-- App\Infrastructure\Http\Controllers\PdfLayoutSettingsController).
--
-- Bisher ließen sich in den PDF-Vorlagen nur Texte (Einleitung/Schluss)
-- bearbeiten, nicht aber, AN WELCHER STELLE im Dokument welcher Block
-- erscheint (Logo, Titel, Adressen, Rechnungsinfo-Block, Einleitungs-/
-- Schlusstext usw.) — das war Kundenwunsch für diese Erweiterung.
--
-- Jede Zeile überschreibt die Position EINES Blocks (`element_key`,
-- siehe App\Domain\Settings\PdfLayout\PdfLayoutBlueprint::ELEMENTS) für
-- GENAU einen Dokumenttyp (`document_type`, siehe
-- App\Domain\Settings\PdfLayout\PdfDocumentType). Fehlt eine Zeile für
-- einen Block, zeichnet der jeweilige *PdfGenerator ihn weiterhin über
-- seine bisherige, fest kodierte Logik — bewusst so gewählt, damit
-- bestehende Installationen ohne Anpassung optisch unverändert bleiben
-- (keine Notwendigkeit, "perfekte" Default-Koordinaten zu pflegen; die
-- im Editor angezeigten Default-Boxen sind nur eine Orientierungshilfe
-- zum Ziehen, keine tatsächlich gerenderten Koordinaten).
--
-- Koordinaten sind in Millimetern, Ursprung oben links (wie in einem
-- Textverarbeitungsprogramm) — nicht im PDF-eigenen pt-Koordinatensystem
-- (Ursprung unten links), das bleibt Implementierungsdetail der
-- *PdfGenerator-Klassen (siehe dort MM_TO_PT-Konstante für die Umrechnung).
CREATE TABLE pdf_layout_elements (
    document_type VARCHAR(40) NOT NULL,
    element_key VARCHAR(40) NOT NULL,
    x_mm DECIMAL(6, 2) NOT NULL,
    y_mm DECIMAL(6, 2) NOT NULL,
    width_mm DECIMAL(6, 2) NULL,
    font_size DECIMAL(5, 2) NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (document_type, element_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --- 126_background_media.sql ---
-- Migration 126: Hintergründe überarbeitet
--  * Darstellung des Oberflächen-Hintergrunds: Deckkraft, Einpassung
--    (cover = Fläche füllen, contain = ganz anzeigen), Ausschnitt (X/Y in %)
--    und Zoom (100–300 %). background_image_filename darf seitdem auch ein
--    kurzes Video (MP4/WebM) sein.
--  * Eigener Hintergrund für die Anmeldeseite (auch Zwei-Faktor-Abfrage)
--    mit denselben Darstellungsoptionen.
ALTER TABLE company_settings
    ADD COLUMN background_opacity        TINYINT UNSIGNED  NOT NULL DEFAULT 100     AFTER background_image_filename,
    ADD COLUMN background_fit            VARCHAR(10)       NOT NULL DEFAULT 'cover' AFTER background_opacity,
    ADD COLUMN background_position_x     TINYINT UNSIGNED  NOT NULL DEFAULT 50      AFTER background_fit,
    ADD COLUMN background_position_y     TINYINT UNSIGNED  NOT NULL DEFAULT 50      AFTER background_position_x,
    ADD COLUMN background_zoom           SMALLINT UNSIGNED NOT NULL DEFAULT 100     AFTER background_position_y,
    ADD COLUMN login_background_filename VARCHAR(255)      NULL                     AFTER background_zoom,
    ADD COLUMN login_background_opacity    TINYINT UNSIGNED  NOT NULL DEFAULT 100     AFTER login_background_filename,
    ADD COLUMN login_background_fit        VARCHAR(10)       NOT NULL DEFAULT 'cover' AFTER login_background_opacity,
    ADD COLUMN login_background_position_x TINYINT UNSIGNED  NOT NULL DEFAULT 50      AFTER login_background_fit,
    ADD COLUMN login_background_position_y TINYINT UNSIGNED  NOT NULL DEFAULT 50      AFTER login_background_position_x,
    ADD COLUMN login_background_zoom       SMALLINT UNSIGNED NOT NULL DEFAULT 100     AFTER login_background_position_y;

-- --- 127_security_settings.sql ---
-- Migration 127: Sicherheits-Einstellungen (Singleton-Zeile), zunächst die
-- automatische Abmeldung nach Inaktivität (Session-Timeout) in Minuten.
-- Nur Administratoren können den Wert unter Einstellungen › Sicherheit ändern.
-- Standard 120 Minuten.

CREATE TABLE security_settings (
    id                      TINYINT           NOT NULL PRIMARY KEY DEFAULT 1,
    session_timeout_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 120,
    CONSTRAINT chk_security_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO security_settings (id, session_timeout_minutes) VALUES (1, 120);

-- --- 128_half_rack.sql ---
-- Migration 128: 9,5-Zoll-Rackeinbaugeräte (Nutzerwunsch).
--  * articles.rack_width: Einbaubreite eines Rackeinbaugeräts (is_19_inch = 1):
--    '19' = volle 19-Zoll-Breite, '9.5' = halbe Breite (9,5 Zoll).
--  * rack_slots.side: bei einem 9,5-Zoll-Gerät in einem 19-Zoll-Rack die
--    belegte Hälfte ('left' | 'right'); NULL = ganze Rackbreite (19-Zoll-Gerät
--    in 19-Zoll-Rack, 9,5-Zoll-Gerät in 9,5-Zoll-Rack). So passen zwei
--    9,5-Zoll-Geräte nebeneinander in ein 19-Zoll-Rack.
-- 9,5-Zoll-Racks gibt es bereits seit Migration 115 (racks.width).
ALTER TABLE articles
    ADD COLUMN rack_width VARCHAR(5) NOT NULL DEFAULT '19' AFTER is_19_inch;

ALTER TABLE rack_slots
    ADD COLUMN side VARCHAR(5) NULL AFTER start_unit;

-- --- 129_cable_rules_fuses.sql ---
-- Migration 129 (Nutzerwunsch):
--  1. Stromverteiler: Schmelzsicherungen (Neozed/Diazed) als weiteres
--     Schutzorgan, drei einzelne 1-polige LS/Sicherungen je Phase, Phase an
--     einphasigen Schutzorganen und an einphasigen Abgängen hinter
--     dreiphasiger Absicherung.
--  2. Stromanschlüsse von Veranstaltungsorten: Absicherung per LS-Schalter
--     (bisher) oder Neozed/Diazed.
--     Hintergründe (Oberfläche/Anmeldung): Unschärfe einstellbar.
--  3. Leitungen: feste Regeln für bestehende Leitungs-Artikel nachziehen
--     (Hersteller "Generisch", immer Zubehör, nicht einzeln vermietbar, kein
--     Einkaufs-/Tagespreis, keine Firmware, kein Rackeinbau, keine
--     Abmessungen, Elektroprüfung nach Leitungstyp, keine Schutzklasse).

-- 1. Stromverteiler
ALTER TABLE power_distributor_devices
    ADD COLUMN fuse_system  VARCHAR(10) NULL AFTER characteristic,              -- neozed | diazed (nur device_type = fuse)
    ADD COLUMN three_single TINYINT(1)  NOT NULL DEFAULT 0 AFTER fuse_system,   -- 3 einzelne 1-polige Geräte (je Phase)
    ADD COLUMN phase        VARCHAR(2)  NULL AFTER three_single;                -- L1 | L2 | L3 bei einphasigen Schutzorganen

ALTER TABLE power_distributor_outlets
    ADD COLUMN phase VARCHAR(2) NULL AFTER device_id;                           -- L1 | L2 | L3 bei dreiphasiger Absicherung

-- 2. Veranstaltungsorte
ALTER TABLE venue_power_connections
    ADD COLUMN fuse_type VARCHAR(10) NOT NULL DEFAULT 'mcb' AFTER breaker_rating; -- mcb | neozed | diazed

-- 2b. Hintergründe: Unschärfe in Pixeln (0–20)
ALTER TABLE company_settings
    ADD COLUMN background_blur       TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER background_zoom,
    ADD COLUMN login_background_blur TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER login_background_zoom;

-- 3. Leitungen (Kategorie mit is_cable = 1)
UPDATE articles a
JOIN article_cables ac ON ac.article_id = a.id
JOIN cable_types ct ON ct.id = ac.cable_type_id
SET a.requires_electrical_inspection = ct.requires_electrical_inspection,
    a.protection_class = NULL
WHERE a.is_bundle = 0;

UPDATE articles a
JOIN categories c ON c.id = a.category_id AND c.is_cable = 1
SET a.dimensions = NULL,
    a.purchase_price = NULL,
    a.is_19_inch = 0, a.rack_width = '19', a.height_units = NULL, a.install_depth_cm = NULL,
    a.has_firmware = 0,
    a.is_billable = 0, a.daily_rental_price = NULL,
    a.is_rentable_individually = 0,
    a.protection_class = NULL
WHERE a.is_bundle = 0;

-- Hersteller "Generisch": IGNORE überspringt Leitungen, deren Präfix es bei
-- "Generisch" schon gibt (Präfix je Hersteller eindeutig) – diese beim
-- nächsten Bearbeiten mit neuem Präfix speichern.
UPDATE IGNORE articles a
JOIN categories c ON c.id = a.category_id AND c.is_cable = 1
JOIN (SELECT id FROM manufacturers WHERE is_generic = 1 ORDER BY name LIMIT 1) g
SET a.manufacturer_id = g.id
WHERE a.is_bundle = 0 AND (a.manufacturer_id IS NULL OR a.manufacturer_id <> g.id);

UPDATE cable_types SET protection_class = NULL;

-- --- 130_rental_blocking_company_holidays.sql ---
-- Migration 130 (Nutzerwunsch):
--  1. Veranstaltungen können den Zeitraum für andere Vermietungen sperren
--     ("exklusiv", Checkbox im Veranstaltungsformular).
--  2. Betriebsurlaub (Einstellungen › Firma › Betriebsurlaub): Veranstaltungen,
--     die in einen Betriebsurlaub fallen, brauchen eine Abholung vor und eine
--     Rückgabe nach dem Urlaub.

ALTER TABLE rentals
    ADD COLUMN blocks_other_rentals TINYINT(1) NOT NULL DEFAULT 0 AFTER deployment_days;

CREATE TABLE company_holidays (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    start_date DATE         NOT NULL,
    end_date   DATE         NOT NULL,
    note       VARCHAR(255) NULL,
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    KEY idx_company_holidays_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 131_account_ledger.sql ---
-- Migration 131 (Nutzerwunsch): Kontoführung je Bankverbindung.
--  - Anfangsbestand je Konto (Betrag und ab welchem Datum er gilt)
--  - Buchungen: Zahlungseingänge/-ausgänge ohne Beleg, abgebuchte Belege,
--    Rechnungszahlungen und aus dem Zahlungsabgleich (MT940/CAMT.053)
--    übernommene Umsätze. amount ist vorzeichenbehaftet (+ Eingang, − Ausgang).

ALTER TABLE bank_accounts
    ADD COLUMN opening_balance      DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER is_default,
    ADD COLUMN opening_balance_date DATE          NULL AFTER opening_balance;

CREATE TABLE account_transactions (
    id              CHAR(36)      NOT NULL PRIMARY KEY,
    bank_account_id CHAR(36)      NOT NULL,
    booking_date    DATE          NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    kind            VARCHAR(20)   NOT NULL,           -- manual | receipt | invoice | import
    description     VARCHAR(255)  NOT NULL,
    counterparty    VARCHAR(255)  NULL,
    receipt_id      CHAR(36)      NULL,
    invoice_id      CHAR(36)      NULL,
    import_hash     CHAR(64)      NULL,               -- verhindert doppelte Übernahme aus dem Zahlungsabgleich
    created_at      DATETIME      NOT NULL,
    updated_at      DATETIME      NOT NULL,
    KEY idx_account_transactions_account_date (bank_account_id, booking_date),
    KEY idx_account_transactions_receipt (receipt_id),
    KEY idx_account_transactions_invoice (invoice_id),
    UNIQUE KEY uq_account_transactions_import (bank_account_id, import_hash),
    CONSTRAINT fk_account_transactions_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 132_bank_account_ledger_enabled.sql ---
-- Migration 132 (Nutzerwunsch): Kontoführung je Firmenkonto in
-- Einstellungen › Bankverbindungen einschaltbar. Konten, für die bereits
-- Buchungen oder ein Anfangsbestand erfasst wurden, bleiben eingeschaltet.

ALTER TABLE bank_accounts
    ADD COLUMN ledger_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default;

UPDATE bank_accounts
SET ledger_enabled = 1
WHERE opening_balance <> 0
   OR opening_balance_date IS NOT NULL
   OR id IN (SELECT DISTINCT bank_account_id FROM account_transactions);

-- --- 133_tax_company_data.sql ---
-- Migration 133 (Nutzerwunsch): Steuer- und Firmendaten für Unternehmen,
-- die regulär Umsatzsteuer abführen (Finanzamt, Rechtsform, Handelsregister,
-- Geschäftsführung), und ein anpassbarer Hinweistext zur
-- Umsatzsteuerbefreiung auf Dokumenten (leer = Standardtext nach § 19 UStG).

ALTER TABLE invoice_settings
    ADD COLUMN tax_office          VARCHAR(150) NULL AFTER vat_id,
    ADD COLUMN legal_form          VARCHAR(100) NULL AFTER tax_office,
    ADD COLUMN register_court      VARCHAR(150) NULL AFTER legal_form,
    ADD COLUMN register_number     VARCHAR(100) NULL AFTER register_court,
    ADD COLUMN managing_directors  VARCHAR(255) NULL AFTER register_number,
    ADD COLUMN small_business_note VARCHAR(500) NULL AFTER managing_directors;

-- --- 134_technicians_trades.sql ---
-- Migration 134 (Nutzerwunsch): Recht "Techniker", Gewerke und
-- Techniker-Einsatzplanung je Veranstaltung.
--  - trades: Gewerke (Einstellungen › Lager & Veranstaltungen › Techniker & Gewerke)
--  - technician_profiles: je Techniker-Benutzer, ob er direkt verplant werden
--    kann oder erst zustimmen muss, sowie Messenger-Kontakt (Mobilnummer für
--    WhatsApp/Signal, Telegram-Chat-ID)
--  - rental_trades: an einer Veranstaltung benötigte Gewerke
--  - rental_technicians: Techniker einer Veranstaltung mit Status
--    (requested | confirmed | declined | assigned), bedienten Gewerken und
--    Erinnerungszähler
--  - technician_settings: wann unbeantwortete Anfragen erneut erinnert werden

CREATE TABLE trades (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    UNIQUE KEY uq_trades_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE technician_profiles (
    user_id               CHAR(36)     NOT NULL PRIMARY KEY,
    requires_confirmation TINYINT(1)   NOT NULL DEFAULT 1,
    phone                 VARCHAR(50)  NULL,
    telegram_chat_id      VARCHAR(100) NULL,
    updated_at            DATETIME     NOT NULL,
    CONSTRAINT fk_technician_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_trades (
    rental_id CHAR(36) NOT NULL,
    trade_id  CHAR(36) NOT NULL,
    PRIMARY KEY (rental_id, trade_id),
    CONSTRAINT fk_rental_trades_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_trades_trade FOREIGN KEY (trade_id) REFERENCES trades (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_technicians (
    id               CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id        CHAR(36)     NOT NULL,
    user_id          CHAR(36)     NOT NULL,
    status           VARCHAR(20)  NOT NULL,
    trade_ids        VARCHAR(1000) NULL,
    note             VARCHAR(500) NULL,
    requested_at     DATETIME     NOT NULL,
    responded_at     DATETIME     NULL,
    reminder_count   INT          NOT NULL DEFAULT 0,
    last_notified_at DATETIME     NULL,
    created_at       DATETIME     NOT NULL,
    updated_at       DATETIME     NOT NULL,
    UNIQUE KEY uq_rental_technicians (rental_id, user_id),
    KEY idx_rental_technicians_user (user_id, status),
    KEY idx_rental_technicians_status (status),
    CONSTRAINT fk_rental_technicians_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_technicians_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE technician_settings (
    id             TINYINT      NOT NULL PRIMARY KEY,
    reminder_hours VARCHAR(100) NOT NULL DEFAULT '24,72',
    updated_at     DATETIME     NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO technician_settings (id, reminder_hours, updated_at) VALUES (1, '24,72', NOW());

-- --- 135_event_audio.sql ---
-- Migration 135 (Nutzerwunsch): Veranstaltungs-Audio
--  - rental_audio_files: Jingles und weitere Audio-Dateien je Veranstaltung
--    (WAV/FLAC/AAC/MP3); minute_programs.jingle_audio_file_id wählt je
--    Minutenprogramm den Jingle
--  - audio_players: Abspielgeräte (Raspberry Pi am Mischpult) mit Geräte-Token
--  - rental_audio_settings: zugeordnetes Abspielgerät + Gong-Vorgabe je Veranstaltung
--  - rental_organizer_logins: zeitlich begrenzbare Veranstalter-Zugänge zur Live-Oberfläche
--  - rental_playback_requests: Warteschlange (Jingle, Audio, Text-Durchsage,
--    Mikrofon-Aufnahme, Stopp), die der Pi abholt
--  - audio_settings: Gong-Datei, Sprachausgabe (Sprache/Stimme/Tempo), Upload-Limit

CREATE TABLE rental_audio_files (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id         CHAR(36)     NOT NULL,
    kind              VARCHAR(20)  NOT NULL,
    title             VARCHAR(160) NOT NULL,
    description       VARCHAR(500) NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename   VARCHAR(255) NOT NULL,
    mime_type         VARCHAR(100) NOT NULL,
    size_bytes        INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order        INT          NOT NULL DEFAULT 0,
    created_at        DATETIME     NOT NULL,
    KEY idx_rental_audio_files_rental (rental_id, kind, sort_order),
    CONSTRAINT fk_rental_audio_files_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE minute_programs
    ADD COLUMN jingle_audio_file_id CHAR(36) NULL AFTER jingle_before;

CREATE TABLE audio_players (
    id             CHAR(36)     NOT NULL PRIMARY KEY,
    name           VARCHAR(120) NOT NULL,
    token_hash     CHAR(64)     NOT NULL,
    token_hint     VARCHAR(20)  NOT NULL,
    enabled        TINYINT(1)   NOT NULL DEFAULT 1,
    last_seen_at   DATETIME     NULL,
    last_ip        VARCHAR(45)  NULL,
    player_version VARCHAR(60)  NULL,
    now_playing    VARCHAR(255) NULL,
    created_at     DATETIME     NOT NULL,
    UNIQUE KEY uq_audio_players_token (token_hash)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_audio_settings (
    rental_id        CHAR(36)   NOT NULL PRIMARY KEY,
    player_id        CHAR(36)   NULL,
    gong_by_default  TINYINT(1) NOT NULL DEFAULT 1,
    updated_at       DATETIME   NOT NULL,
    KEY idx_rental_audio_settings_player (player_id),
    CONSTRAINT fk_rental_audio_settings_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_audio_settings_player FOREIGN KEY (player_id) REFERENCES audio_players (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_organizer_logins (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id       CHAR(36)     NOT NULL,
    label           VARCHAR(120) NOT NULL,
    username        VARCHAR(80)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    valid_from      DATETIME     NULL,
    valid_until     DATETIME     NULL,
    enabled         TINYINT(1)   NOT NULL DEFAULT 1,
    failed_attempts INT          NOT NULL DEFAULT 0,
    locked_until    DATETIME     NULL,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL,
    UNIQUE KEY uq_rental_organizer_logins_username (username),
    KEY idx_rental_organizer_logins_rental (rental_id),
    CONSTRAINT fk_rental_organizer_logins_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_playback_requests (
    id                        CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id                 CHAR(36)     NOT NULL,
    kind                      VARCHAR(20)  NOT NULL,
    audio_file_id             CHAR(36)     NULL,
    label                     VARCHAR(160) NULL,
    text                      TEXT         NULL,
    recording_stored_filename VARCHAR(255) NULL,
    recording_mime_type       VARCHAR(100) NULL,
    with_gong                 TINYINT(1)   NOT NULL DEFAULT 0,
    status                    VARCHAR(20)  NOT NULL DEFAULT 'queued',
    requested_by_type         VARCHAR(20)  NOT NULL,
    requested_by_id           CHAR(36)     NULL,
    requested_by_name         VARCHAR(160) NOT NULL,
    player_id                 CHAR(36)     NULL,
    error                     VARCHAR(500) NULL,
    created_at                DATETIME     NOT NULL,
    started_at                DATETIME     NULL,
    finished_at               DATETIME     NULL,
    KEY idx_rental_playback_requests_queue (rental_id, status, created_at),
    CONSTRAINT fk_rental_playback_requests_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE audio_settings (
    id                     TINYINT      NOT NULL PRIMARY KEY,
    gong_original_filename VARCHAR(255) NULL,
    gong_stored_filename   VARCHAR(255) NULL,
    gong_mime_type         VARCHAR(100) NULL,
    tts_language           VARCHAR(20)  NOT NULL DEFAULT 'de-DE',
    tts_voice              VARCHAR(100) NULL,
    tts_rate               DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    max_upload_mb          INT          NOT NULL DEFAULT 50,
    queue_expiry_minutes   INT          NOT NULL DEFAULT 10,
    updated_at             DATETIME     NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO audio_settings (id, tts_language, tts_rate, max_upload_mb, queue_expiry_minutes, updated_at)
VALUES (1, 'de-DE', 1.00, 50, 10, NOW());

-- --- 136_scheduled_playbacks.sql ---
-- Migration 136: zeitlich geplante Audio-Einspieler (Jingles / Audio-Dateien)
--  - scheduled_playbacks: geplante Wiedergaben je Veranstaltung; zur Startzeit
--    legt der Server daraus einen normalen Abspielauftrag an (beim Abruf durch
--    das Abspielgerät bzw. die Live-Oberfläche, kein Cronjob nötig)
--  - rental_organizer_logins.can_schedule: Veranstalter-Zugang darf planen
--    (inkl. eigener Audio-Uploads); muss beim Anlegen bewusst erlaubt werden

ALTER TABLE rental_organizer_logins
    ADD COLUMN can_schedule TINYINT(1) NOT NULL DEFAULT 0 AFTER enabled;

CREATE TABLE IF NOT EXISTS scheduled_playbacks (
    id CHAR(36) NOT NULL PRIMARY KEY,
    rental_id CHAR(36) NOT NULL,
    audio_file_id CHAR(36) NOT NULL,
    play_at DATETIME NOT NULL,
    note VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    playback_request_id CHAR(36) NULL,
    created_by_type VARCHAR(20) NOT NULL,
    created_by_id CHAR(36) NULL,
    created_by_name VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL,
    triggered_at DATETIME NULL,
    KEY idx_scheduled_playbacks_due (rental_id, status, play_at),
    CONSTRAINT fk_scheduled_playbacks_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_scheduled_playbacks_file FOREIGN KEY (audio_file_id) REFERENCES rental_audio_files (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 137_rental_audio_logo.sql ---
-- Migration 137: eigenes Logo je Veranstaltung für die Live-Oberfläche
-- (Audio-Dashboard). Wird nur Veranstalter-Zugängen angezeigt; Techniker
-- und Admins sehen immer das Firmenlogo.
ALTER TABLE rental_audio_settings
    ADD COLUMN logo_filename VARCHAR(255) NULL AFTER gong_by_default;

-- --- 138_audio_upload_language.sql ---
-- Migration 138: Upload-Recht für Veranstalter-Zugänge und Sprache der Durchsagen
--  - rental_organizer_logins.can_upload: darf eigene Sounddateien hochladen
--    (Techniker/Admins dürfen das immer)
--  - rental_audio_settings.tts_language: feste Sprache für getippte Durchsagen
--    dieser Veranstaltung; NULL = Sprache wird in der Live-Oberfläche gewählt
--  - rental_playback_requests.language: Sprache je Durchsage (an das Abspielgerät)
ALTER TABLE rental_organizer_logins
    ADD COLUMN can_upload TINYINT(1) NOT NULL DEFAULT 0 AFTER can_schedule;
ALTER TABLE rental_audio_settings
    ADD COLUMN tts_language VARCHAR(10) NULL AFTER logo_filename;
ALTER TABLE rental_playback_requests
    ADD COLUMN language VARCHAR(10) NULL AFTER text;

-- --- 139_recurring_playbacks.sql ---
-- Migration 139: zyklische Einspieler (z. B. alle 10 Minuten, jede Stunde)
-- Ein geplanter Einspieler mit repeat_interval_minutes ist eine Serie: nach
-- jeder Wiedergabe rückt play_at um das Intervall vor, bis repeat_until bzw.
-- repeat_max erreicht ist (spätestens zum Ende des Veranstaltungszeitraums).
ALTER TABLE scheduled_playbacks
    ADD COLUMN repeat_interval_minutes INT NULL AFTER note,
    ADD COLUMN repeat_until DATETIME NULL AFTER repeat_interval_minutes,
    ADD COLUMN repeat_max INT NULL AFTER repeat_until,
    ADD COLUMN repeat_count INT NOT NULL DEFAULT 0 AFTER repeat_max;

-- --- 140_multilingual_announcements.sql ---
-- Migration 140: mehrsprachige Durchsagen und Übersetzer
--  - rental_audio_settings.tts_language: jetzt Liste (kommagetrennt), z. B.
--    "de-DE,en-GB"; leer = Sprache wird in der Live-Oberfläche gewählt
--  - audio_settings: Übersetzer (none | openai | deepl), DeepL-Schlüssel,
--    OpenAI-Modell (der OpenAI-Schlüssel kommt aus den KI-Werbebildern)
ALTER TABLE rental_audio_settings
    MODIFY COLUMN tts_language VARCHAR(255) NULL;
ALTER TABLE audio_settings
    ADD COLUMN translator_provider VARCHAR(10) NOT NULL DEFAULT 'none' AFTER queue_expiry_minutes,
    ADD COLUMN deepl_api_key VARCHAR(255) NULL AFTER translator_provider,
    ADD COLUMN openai_translation_model VARCHAR(60) NOT NULL DEFAULT 'gpt-4o-mini' AFTER deepl_api_key;

-- --- 141_audio_player_pairing.sql ---
-- Migration 141: Abspielgerät per 6-stelligem Code koppeln
--  - audio_pairing_codes: kurzlebige Kopplungs-Codes je Veranstaltung. Der Code
--    wird in der Veranstaltung unter "Audio & Durchsagen" erzeugt und am
--    Abspielgerät (AutoEventPlay) eingegeben. Das Gerät meldet sich damit an
--    POST /api/player/v1/pair, erhält seinen Geräte-Token und wird der
--    Veranstaltung zugeordnet.
--  - audio_pairing_failures: Fehlversuche je IP (Schutz gegen Durchprobieren)

CREATE TABLE audio_pairing_codes (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id       CHAR(36)     NOT NULL,
    code            CHAR(6)      NOT NULL,
    created_by_name VARCHAR(160) NOT NULL,
    created_at      DATETIME     NOT NULL,
    expires_at      DATETIME     NOT NULL,
    used_at         DATETIME     NULL,
    player_id       CHAR(36)     NULL,
    KEY idx_audio_pairing_codes_code (code, expires_at),
    KEY idx_audio_pairing_codes_rental (rental_id),
    CONSTRAINT fk_audio_pairing_codes_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE audio_pairing_failures (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45)     NOT NULL,
    attempted_at DATETIME        NOT NULL,
    KEY idx_audio_pairing_failures_ip (ip, attempted_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --- 142_dry_hire.sql ---
-- Migration 141: Dry-Hire-Preise
--  - article_dry_hire_prices: Dry-Hire-Tagesmietsatz je Artikel (zusätzlich
--    zum normalen Tagesmietsatz); ohne Eintrag gilt der normale Preis
--  - customer_dry_hire: Kunden, die Dry-Hire-Preise bekommen
--  - dry_hire_tiers: Staffel „ab Tag X kostet jeder weitere Tag P % des
--    Dry-Hire-Tagesmietsatzes“ – gilt für beliebig lange Mietdauer
--  - document_dry_hire: ob ein Angebot/AB/Rechnung mit Dry-Hire-Preisen
--    erstellt wurde (Checkbox im Formular)
CREATE TABLE IF NOT EXISTS article_dry_hire_prices (
    article_id CHAR(36) NOT NULL PRIMARY KEY,
    daily_price DECIMAL(10,2) NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_dry_hire_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_dry_hire (
    customer_id CHAR(36) NOT NULL PRIMARY KEY,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_dry_hire_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dry_hire_tiers (
    from_day INT NOT NULL PRIMARY KEY,
    percentage DECIMAL(6,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO dry_hire_tiers (from_day, percentage) VALUES (1, 100.00), (2, 50.00), (8, 25.00);

CREATE TABLE IF NOT EXISTS document_dry_hire (
    document_type VARCHAR(30) NOT NULL,
    document_id CHAR(36) NOT NULL,
    enabled TINYINT(1) NOT NULL,
    PRIMARY KEY (document_type, document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 143_datev_export.sql ---
-- Migration 143 (Nutzerwunsch): DATEV-Export für den Steuerberater
--  - datev_settings: Einstellungen (Einstellungen → Integrationen → DATEV),
--    Singleton-Zeile wie integration_base_settings. Kontenfelder NULL =
--    Vorschlagskonto des gewählten Kontenrahmens (SKR 03/04).
--  - datev_exports: Protokoll erzeugter/versendeter Exporte; verhindert
--    außerdem, dass der automatische Versand (bin/send-datev-export.php)
--    denselben Zeitraum mehrfach verschickt.
CREATE TABLE IF NOT EXISTS datev_settings (
    id                          TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    enabled                     TINYINT(1)   NOT NULL DEFAULT 0,
    consultant_number           INT UNSIGNED NULL,
    client_number               INT UNSIGNED NULL,
    chart_of_accounts           CHAR(2)      NOT NULL DEFAULT '03',
    account_length              TINYINT      NOT NULL DEFAULT 4,
    fiscal_year_start_month     TINYINT      NOT NULL DEFAULT 1,
    debtor_mode                 VARCHAR(20)  NOT NULL DEFAULT 'collective',
    debtor_account              INT UNSIGNED NULL,
    creditor_account            INT UNSIGNED NULL,
    account_revenue_19          INT UNSIGNED NULL,
    account_revenue_7           INT UNSIGNED NULL,
    account_revenue_tax_free    INT UNSIGNED NULL,
    account_revenue_small_business INT UNSIGNED NULL,
    account_expense_goods       INT UNSIGNED NULL,
    account_expense_tools       INT UNSIGNED NULL,
    account_expense_vehicle     INT UNSIGNED NULL,
    account_expense_other       INT UNSIGNED NULL,
    receipt_tax_key             VARCHAR(4)   NULL,
    include_receipts            TINYINT(1)   NOT NULL DEFAULT 1,
    include_documents           TINYINT(1)   NOT NULL DEFAULT 1,
    advisor_email               VARCHAR(255) NULL,
    auto_send_enabled           TINYINT(1)   NOT NULL DEFAULT 0,
    export_interval             VARCHAR(20)  NOT NULL DEFAULT 'monthly',
    send_day                    TINYINT      NOT NULL DEFAULT 5,
    updated_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_datev_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO datev_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS datev_exports (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    period_from     DATE         NOT NULL,
    period_to       DATE         NOT NULL,
    export_trigger  VARCHAR(20)  NOT NULL,
    recipient       VARCHAR(255) NULL,
    booking_count   INT          NOT NULL DEFAULT 0,
    document_count  INT          NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL,
    INDEX idx_datev_exports_period (period_from, period_to),
    INDEX idx_datev_exports_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 144_inventory_number_current_values.sql ---
-- Migration 144: Zeitwerte für die Versicherungsliste einer Veranstaltung
--  - inventory_numbers.current_value*: geschätzter Zeitwert direkt an der
--    Inventarnummer (händisch im Inventarnummern-Formular gepflegt oder per KI
--    geschätzt, siehe current_value_source/current_value_note)
--  - article_fixed_current_values: fester Zeitwert je Artikel, gilt für alle
--    Exemplare ohne eigenen Zeitwert (z. B. XLR-Kabel)
--  - insurance_export_settings: zuletzt gewählte Spalten des
--    Versicherungslisten-Exports (CSV/PDF)
-- Idempotent geschrieben; übernimmt außerdem Werte aus der Vorab-Version
-- dieser Funktion (eigene Tabelle inventory_number_current_values).

ALTER TABLE inventory_numbers
    ADD COLUMN IF NOT EXISTS current_value            DECIMAL(10,2) NULL AFTER purchase_price,
    ADD COLUMN IF NOT EXISTS current_value_source     VARCHAR(10)   NULL AFTER current_value,
    ADD COLUMN IF NOT EXISTS current_value_note       VARCHAR(500)  NULL AFTER current_value_source,
    ADD COLUMN IF NOT EXISTS current_value_updated_at DATETIME      NULL AFTER current_value_note;

CREATE TABLE IF NOT EXISTS article_fixed_current_values (
    article_id    CHAR(36)      NOT NULL PRIMARY KEY,
    current_value DECIMAL(10,2) NOT NULL,
    updated_at    DATETIME      NOT NULL,
    CONSTRAINT fk_article_fixed_current_value_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS insurance_export_settings (
    id      TINYINT      NOT NULL PRIMARY KEY,
    fields  VARCHAR(255) NOT NULL,
    CONSTRAINT chk_insurance_export_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT IGNORE INTO insurance_export_settings (id, fields)
VALUES (1, 'category,manufacturer_model,inventory_number,acquisition_year,current_value,purchase_price');

-- Vorab-Version: Werte aus der früheren eigenen Tabelle übernehmen und diese entfernen.
CREATE TABLE IF NOT EXISTS inventory_number_current_values (
    inventory_number_id CHAR(36)      NOT NULL PRIMARY KEY,
    current_value       DECIMAL(10,2) NOT NULL,
    source              VARCHAR(10)   NOT NULL DEFAULT 'manual',
    note                VARCHAR(500)  NULL,
    updated_at          DATETIME      NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

UPDATE inventory_numbers i
    JOIN inventory_number_current_values v ON v.inventory_number_id = i.id
SET i.current_value = v.current_value,
    i.current_value_source = v.source,
    i.current_value_note = v.note,
    i.current_value_updated_at = v.updated_at
WHERE i.current_value IS NULL;

DROP TABLE inventory_number_current_values;

-- --- 145_trusted_devices.sql ---
-- Migration 145: "Dem Gerät für die nächsten 10 Tage vertrauen"
--  - trusted_devices: Geräte (Browser), auf denen ein Benutzer nach
--    erfolgreicher Zwei-Faktor-Anmeldung den Haken "Dem Gerät vertrauen"
--    gesetzt hat. Bis expires_at entfällt dort die Abfrage des zweiten
--    Faktors (Passwort bleibt immer Pflicht).
--  - Im Browser liegt nur ein Cookie "selector:validator". Gespeichert wird
--    hier der selector im Klartext (zum Nachschlagen) und vom validator nur
--    der SHA-256-Hash — ein Datenbank-Leck allein reicht also nicht, um
--    einem Gerät "vertrauen" zu lassen.
--  - Alle Einträge eines Benutzers werden gelöscht, sobald er sein Passwort
--    oder seinen zweiten Faktor ändert (bzw. ein Administrator das tut),
--    sowie manuell über Profil › Sicherheit.

CREATE TABLE trusted_devices (
    id            CHAR(36)     NOT NULL PRIMARY KEY,
    user_id       VARCHAR(36)  NOT NULL,
    selector      CHAR(24)     NOT NULL,
    token_hash    CHAR(64)     NOT NULL,
    user_agent    TEXT         NULL,
    ip_address    VARCHAR(45)  NULL,
    created_at    DATETIME     NOT NULL,
    expires_at    DATETIME     NOT NULL,
    UNIQUE KEY uq_trusted_devices_selector (selector),
    KEY idx_trusted_devices_user (user_id, expires_at),
    CONSTRAINT fk_trusted_devices_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =======================================================================
-- Stand dieses Snapshots (maschinell ausgewertet — NICHT entfernen).
--
-- Die folgende Liste nennt jede Migration aus database/migrations/, deren
-- Wirkung in diesem schema.sql bereits enthalten ist. Der Web-Installer
-- (public/install.php, applyFreshSchema()) spielt nach diesem Snapshot
-- über den MigrationRunner alle Migrationen ein, die hier NICHT stehen;
-- dasselbe gilt für "mysql < database/schema.sql" gefolgt von
-- "php bin/migrate.php". Ein veralteter Snapshot führt damit nicht mehr
-- zu einer unvollständigen Neuinstallation.
--
-- Beim Hinzufügen einer Migration: ihren Inhalt oben als eigenen
-- "-- --- NNN_name.sql ---"-Abschnitt anhängen UND ihren Dateinamen hier
-- eintragen (tests/unit/Infrastructure/Migrations/SchemaSnapshotTest
-- schlägt sonst fehl). Niemals eine Migration eintragen, deren Inhalt
-- oben fehlt.
--
-- Tabellendefinition identisch zu MigrationRunner::ensureMigrationsTable().
-- =======================================================================
CREATE TABLE IF NOT EXISTS schema_migrations (
    migration  VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at DATETIME     NOT NULL
);

INSERT INTO schema_migrations (migration, applied_at) VALUES
    ('001_add_prefixes.sql', NOW()),
    ('002_add_customers.sql', NOW()),
    ('003_add_cases.sql', NOW()),
    ('004_add_suppliers.sql', NOW()),
    ('005_add_rentals.sql', NOW()),
    ('006_add_prices_and_customer_number.sql', NOW()),
    ('007_add_accessories_and_returns.sql', NOW()),
    ('008_add_users_and_auth.sql', NOW()),
    ('009_add_electrical_inspection_flag.sql', NOW()),
    ('010_add_electrical_inspections.sql', NOW()),
    ('011_add_venues.sql', NOW()),
    ('012_add_billing_fields.sql', NOW()),
    ('013_add_price_tiers.sql', NOW()),
    ('014_make_event_location_nullable.sql', NOW()),
    ('015_add_company_settings.sql', NOW()),
    ('016_add_legal_settings.sql', NOW()),
    ('017_add_webauthn_credentials.sql', NOW()),
    ('018_add_user_default_page_size.sql', NOW()),
    ('019_add_user_locale.sql', NOW()),
    ('020_add_label_printers.sql', NOW()),
    ('021_add_offers.sql', NOW()),
    ('022_add_company_logo_options.sql', NOW()),
    ('023_add_user_names_password_setup_and_two_factor_enforcement.sql', NOW()),
    ('024_add_venue_power_connections.sql', NOW()),
    ('025_add_user_sessions.sql', NOW()),
    ('026_add_company_brand_name.sql', NOW()),
    ('027_add_smtp_reply_to_and_archive_bcc.sql', NOW()),
    ('028_add_email_templates.sql', NOW()),
    ('029_add_smtp_archive_categories.sql', NOW()),
    ('030_add_offer_pdf_template.sql', NOW()),
    ('031_add_invoice_settings_and_sequence.sql', NOW()),
    ('032_add_invoice_pdf_template.sql', NOW()),
    ('033_add_invoices.sql', NOW()),
    ('034_add_invoice_email_template.sql', NOW()),
    ('035_add_receipts.sql', NOW()),
    ('036_add_inventory_number_receipt.sql', NOW()),
    ('037_add_pricing_settings_and_invoice_discounts.sql', NOW()),
    ('038_add_offer_accessory_flat_rate_and_discount.sql', NOW()),
    ('039_add_mailbox_settings.sql', NOW()),
    ('040_add_receipt_inbox_allowed_senders.sql', NOW()),
    ('041_add_receipt_inbox_messages.sql', NOW()),
    ('042_add_user_notify_on_new_receipt_email.sql', NOW()),
    ('043_add_push_settings_and_subscriptions.sql', NOW()),
    ('044_add_document_storage_targets.sql', NOW()),
    ('045_add_document_storage_assignments.sql', NOW()),
    ('046_add_document_storage_upload_log.sql', NOW()),
    ('047_add_rma_kind_and_worked_hours.sql', NOW()),
    ('048_add_rma_receipts.sql', NOW()),
    ('049_add_labor_time_articles.sql', NOW()),
    ('050_add_offer_labor_items.sql', NOW()),
    ('051_add_invoice_labor_items.sql', NOW()),
    ('052_add_dunning.sql', NOW()),
    ('053_add_dunning_notice_document_type.sql', NOW()),
    ('054_add_article_active_flag.sql', NOW()),
    ('055_add_warehouses.sql', NOW()),
    ('056_widen_price_tiers_day_count.sql', NOW()),
    ('057_add_company_background_image.sql', NOW()),
    ('058_add_rma_package_photos.sql', NOW()),
    ('059_add_article_channel_listings.sql', NOW()),
    ('060_add_integration_settings.sql', NOW()),
    ('061_add_api_tokens.sql', NOW()),
    ('062_add_calendars.sql', NOW()),
    ('063_add_caldav_signing_settings.sql', NOW()),
    ('064_convert_pdf_settings_to_rich_html.sql', NOW()),
    ('065_add_carddav_credentials.sql', NOW()),
    ('066_add_pdf_design_settings.sql', NOW()),
    ('067_add_rental_reminders.sql', NOW()),
    ('068_add_overage_settings.sql', NOW()),
    ('069_add_inventory_number_storage_location.sql', NOW()),
    ('070_add_article_19_inch_fields.sql', NOW()),
    ('071_add_rental_customer_checklist.sql', NOW()),
    ('072_add_inventory_number_sales.sql', NOW()),
    ('073_add_vehicles.sql', NOW()),
    ('074_add_vouchers.sql', NOW()),
    ('075_add_impersonations.sql', NOW()),
    ('076_add_firmware.sql', NOW()),
    ('077_add_messaging.sql', NOW()),
    ('078_add_purchase_orders_and_sale_stock.sql', NOW()),
    ('081_add_minute_programs.sql', NOW()),
    ('082_add_rental_terms_and_id_check.sql', NOW()),
    ('083_add_notice_documents.sql', NOW()),
    ('084_add_document_numbering_and_order_confirmations.sql', NOW()),
    ('085_add_offer_invoice_free_text_items.sql', NOW()),
    ('086_add_invoice_due_reminders.sql', NOW()),
    ('087_add_article_pre_issue_checks.sql', NOW()),
    ('088_add_ai_ad_images.sql', NOW()),
    ('089_backfill_missing_pricing_settings_row.sql', NOW()),
    ('090_add_dashboard_widgets.sql', NOW()),
    ('091_add_pdf_layout_elements.sql', NOW()),
    ('092_add_closing_text.sql', NOW()),
    ('093_vehicle_load_area_dimensions.sql', NOW()),
    ('094_invoice_flatrate_logistics_rental_link_templates.sql', NOW()),
    ('095_customer_number.sql', NOW()),
    ('096_protection_class_and_inspection_measurements.sql', NOW()),
    ('097_labor_item_user.sql', NOW()),
    ('098_pricing_rounding.sql', NOW()),
    ('099_voucher_customer_restriction.sql', NOW()),
    ('100_customer_contacts.sql', NOW()),
    ('101_labor_time_daily_rate.sql', NOW()),
    ('102_optional_document_period.sql', NOW()),
    ('103_offer_order_confirmation_rental_link.sql', NOW()),
    ('104_contact_address_and_document_link.sql', NOW()),
    ('105_bank_accounts.sql', NOW()),
    ('106_payment_methods.sql', NOW()),
    ('107_article_bundles.sql', NOW()),
    ('108_company_contact.sql', NOW()),
    ('109_article_document_fields.sql', NOW()),
    ('110_pdf_currency_display.sql', NOW()),
    ('111_bundle_alternatives.sql', NOW()),
    ('112_company_favicon.sql', NOW()),
    ('113_article_sets.sql', NOW()),
    ('114_case_images_notice.sql', NOW()),
    ('115_racks.sql', NOW()),
    ('116_pdf_fonts.sql', NOW()),
    ('117_power_distributors.sql', NOW()),
    ('118_power_distributor_category.sql', NOW()),
    ('119_generic_manufacturer.sql', NOW()),
    ('120_case_rack_numbering.sql', NOW()),
    ('121_invoice_corrections.sql', NOW()),
    ('122_invoice_correction_email.sql', NOW()),
    ('123_invoice_correction_document_storage.sql', NOW()),
    ('124_rack_exemplars_case_numbers.sql', NOW()),
    ('125_cables.sql', NOW()),
    ('126_background_media.sql', NOW()),
    ('127_security_settings.sql', NOW()),
    ('128_half_rack.sql', NOW()),
    ('129_cable_rules_fuses.sql', NOW()),
    ('130_rental_blocking_company_holidays.sql', NOW()),
    ('131_account_ledger.sql', NOW()),
    ('132_bank_account_ledger_enabled.sql', NOW()),
    ('133_tax_company_data.sql', NOW()),
    ('134_technicians_trades.sql', NOW()),
    ('135_event_audio.sql', NOW()),
    ('136_scheduled_playbacks.sql', NOW()),
    ('137_rental_audio_logo.sql', NOW()),
    ('138_audio_upload_language.sql', NOW()),
    ('139_recurring_playbacks.sql', NOW()),
    ('140_multilingual_announcements.sql', NOW()),
    ('141_audio_player_pairing.sql', NOW()),
    ('142_dry_hire.sql', NOW()),
    ('143_datev_export.sql', NOW()),
    ('144_inventory_number_current_values.sql', NOW()),
    ('145_trusted_devices.sql', NOW()),
    ('148_storage_folders.sql', NOW());
