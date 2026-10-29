-- Materialverleih Software — Datenbankschema (MariaDB 10.6+)
-- Zeichensatz/Kollation durchgängig utf8mb4 für Sonderzeichen & Emojis.

CREATE DATABASE IF NOT EXISTS materialverleih
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE materialverleih;

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
    manufacturer_id     CHAR(36)        NOT NULL,
    prefix              VARCHAR(20)     NULL, -- Präfix für Inventurnummern, z. B. "AKS" (siehe inventory_numbers)
    category_id         CHAR(36)        NULL,
    description         TEXT            NULL,
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
    background_image_filename VARCHAR(255) NULL, -- optionales Hintergrundbild für die gesamte Oberfläche, siehe App\Infrastructure\Http\CompanyBranding::backgroundImageUrl()
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
    protocol                 ENUM('webdav', 'ftps', 'sftp', 'scp', 's3') NOT NULL,
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

-- --- 031_add_invoice_settings_and_sequence.sql -------------------------
CREATE TABLE invoice_sequences (
    id           TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    next_number  INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT chk_invoice_sequences_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO invoice_sequences (id, next_number) VALUES (1, 1);

-- --- 052_add_dunning.sql ------------------------------------------------
CREATE TABLE dunning_sequences (
    id           TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    next_number  INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT chk_dunning_sequences_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO dunning_sequences (id, next_number) VALUES (1, 1);

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
