-- Generisches Nummerierungssystem je Dokumenttyp (Rechnungen, Angebote,
-- Mahnungen, Auftragsbestätigungen) + neue Dokumentenart
-- "Auftragsbestätigung" (siehe App\Domain\Numbering\*,
-- App\Infrastructure\Persistence\Pdo\DocumentNumberSequence,
-- App\Domain\OrderConfirmation\OrderConfirmation).
--
-- Ersetzt die bisherigen, type-spezifischen Sequenz-Tabellen
-- invoice_sequences/dunning_sequences sowie das feste
-- invoice_settings.invoice_number_prefix-Feld durch eine einzige, admin-
-- konfigurierbare Lösung für alle vier Dokumenttypen.
--
-- Hinweis zur Nummerierung dieser Migrationsdatei: der Projekt-
-- Architekturdoc schlägt "083_..." vor, diese Nummer wurde jedoch bereits
-- von 083_add_notice_documents.sql belegt — daher hier 084.

-- ---------------------------------------------------------------------
-- 1) Generische Nummerierungseinstellungen je Dokumenttyp
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

-- Startwerte: Rechnungen/Mahnungen behalten exakt ihr bisheriges Format
-- (Präfix inkl. Bindestrich + 6-stellige, nie zurückgesetzte Ziffernfolge),
-- Angebote/Auftragsbestätigungen erhalten ein analoges neues Format.
INSERT INTO document_numbering_settings (document_type, scheme, prefix, `separator`, digit_count) VALUES
    ('invoice', 'simple', 'RE-', '-', 6),
    ('offer', 'simple', 'AN-', '-', 6),
    ('dunning_notice', 'simple', 'MAH-', '-', 6),
    ('order_confirmation', 'simple', 'AB-', '-', 6);

-- ---------------------------------------------------------------------
-- 2) Generische Zähler-Tabelle (PK: document_type, sequence_year — siehe
--    DocumentNumberSequence-Klassen-Doc für das race-condition-sichere
--    SELECT ... FOR UPDATE-Muster und den Jahreswechsel-Reset).
-- ---------------------------------------------------------------------
CREATE TABLE document_number_sequences (
    document_type  VARCHAR(30)  NOT NULL,
    sequence_year  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_number    INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (document_type, sequence_year)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Übernimmt die bisherigen Zählerstände 1:1, damit keine Rechnungs-/
-- Mahnungsnummer doppelt vergeben wird.
INSERT INTO document_number_sequences (document_type, sequence_year, next_number)
    SELECT 'invoice', 0, next_number FROM invoice_sequences WHERE id = 1;
INSERT INTO document_number_sequences (document_type, sequence_year, next_number)
    SELECT 'dunning_notice', 0, next_number FROM dunning_sequences WHERE id = 1;
INSERT INTO document_number_sequences (document_type, sequence_year, next_number) VALUES
    ('order_confirmation', 0, 1);

DROP TABLE invoice_sequences;
DROP TABLE dunning_sequences;

ALTER TABLE invoice_settings DROP COLUMN invoice_number_prefix;

-- ---------------------------------------------------------------------
-- 3) offers.offer_number — ab jetzt bei Erstellung sofort und verbindlich
--    vergeben (siehe CreateOfferHandler), anders als Invoice::invoiceNumber
--    daher NICHT nullable. Bestehende Angebote werden hier anhand ihres
--    Erstellungsdatums nachnummeriert (älteste zuerst = kleinste Nummer),
--    und der Angebots-Zähler wird anschließend auf die nächste freie
--    Nummer vorgesetzt.
-- ---------------------------------------------------------------------
ALTER TABLE offers ADD COLUMN offer_number VARCHAR(50) NULL AFTER id;

SET @offer_rownum = 0;
UPDATE offers
    SET offer_number = CONCAT('AN-', LPAD(@offer_rownum := @offer_rownum + 1, 6, '0'))
    ORDER BY created_at ASC, id ASC;

UPDATE document_number_sequences
    SET next_number = @offer_rownum + 1
    WHERE document_type = 'offer' AND sequence_year = 0;

ALTER TABLE offers
    MODIFY COLUMN offer_number VARCHAR(50) NOT NULL,
    ADD CONSTRAINT uq_offers_offer_number UNIQUE (offer_number);

-- ---------------------------------------------------------------------
-- 4) Auftragsbestätigungen — vereinfachte Geschwister-Entität zu Offer:
--    Kunde, Veranstaltungsort, Zeitraum, Zubehörpauschale + Kombirabatt
--    (Snapshot, siehe App\Domain\Pricing\DocumentTotals), Notiz. KEIN
--    validUntil (anders als Offer), KEINE Arbeitszeit-/Verkaufspositionen
--    und KEIN E-Mail-Workflow (bewusst reduzierter Funktionsumfang
--    gegenüber Offer) — nur PDF-Download.
--    Status-Workflow Entwurf -> Versendet -> Bestätigt, + Storniert (aus
--    Entwurf/Versendet), analog InvoiceStatus.
-- ---------------------------------------------------------------------
CREATE TABLE order_confirmations (
    id                    CHAR(36)      NOT NULL PRIMARY KEY,
    order_confirmation_number VARCHAR(50) NOT NULL,
    customer_id           CHAR(36)      NULL,
    event_location        VARCHAR(255)  NULL,
    venue_id              CHAR(36)      NULL,
    start_date            DATE          NOT NULL,
    end_date              DATE          NOT NULL,
    status                VARCHAR(20)   NOT NULL DEFAULT 'draft',
    note                  TEXT          NULL,
    accessory_flat_rate_enabled TINYINT(1)    NOT NULL DEFAULT 1,
    accessory_flat_rate_percent DECIMAL(5, 2) NULL,
    discount_type         ENUM('percent', 'fixed') NULL,
    discount_value        DECIMAL(10, 2) NULL,
    source_offer_id       CHAR(36)      NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_order_confirmations_number UNIQUE (order_confirmation_number),
    CONSTRAINT fk_order_confirmations_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_order_confirmations_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    CONSTRAINT fk_order_confirmations_source_offer
        FOREIGN KEY (source_offer_id) REFERENCES offers (id) ON DELETE SET NULL,
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

-- Freitext-Posten und Zwischentexte (Zwischenüberschriften ohne Preis) —
-- gemeinsam mit den regulären Positionen frei anordenbar über sort_order
-- (siehe orderedRows()-Konzept, App\Application\OrderConfirmation\Query\
-- GetOrderConfirmationHandler sowie die identischen Tabellen auf Angeboten/
-- Rechnungen, Migration 085).
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
