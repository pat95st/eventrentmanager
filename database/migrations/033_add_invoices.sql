-- Migration für bereits bestehende Installationen: Rechnungen als eigene,
-- von Angeboten (offers) und Veranstaltungen (rentals) unabhängige Entität,
-- siehe App\Domain\Invoice\Invoice und App\Domain\Invoice\InvoiceItem.
-- Strukturell an die offers-Tabelle (Migration 021) angelehnt, mit
-- rechnungsspezifischen Zusatzfeldern für die fortlaufende Rechnungsnummer,
-- das Rechnungs-/Fälligkeitsdatum, die Referenz des Kunden (BT-10, siehe
-- XRechnungGenerator) sowie dem beim Versenden gesnapshoteten
-- Kleinunternehmer-/Steuersatz-Stand (siehe MarkInvoiceSentHandler).
--
-- Eine Rechnung kann eigenständig, aus einem Angebot (source_offer_id) oder
-- aus einer Veranstaltung (source_rental_id) entstehen; beide Verweise sind
-- rein informativ (ON DELETE SET NULL), die Rechnung bleibt beim Löschen der
-- Quelle unverändert bestehen.
USE materialverleih;

CREATE TABLE invoices (
    id                  CHAR(36)       NOT NULL PRIMARY KEY,
    -- Bewusst nullable wie bei offers: eine Rechnung kann als Entwurf ohne
    -- endgültige Kundenzuordnung begonnen werden.
    customer_id         CHAR(36)       NULL,
    event_location      VARCHAR(255)   NULL,
    venue_id            CHAR(36)       NULL,
    start_date          DATE           NOT NULL,
    end_date            DATE           NOT NULL,
    -- Bleiben NULL, bis die Rechnung als versendet markiert wird (siehe
    -- MarkInvoiceSentHandler / InvoiceNumberSequence) — erst dann wird die
    -- gesetzlich relevante Rechnungsnummer vergeben.
    invoice_number      VARCHAR(32)    NULL UNIQUE,
    invoice_date        DATE           NULL,
    due_date             DATE          NULL,
    buyer_reference     VARCHAR(255)   NULL,
    status              VARCHAR(20)    NOT NULL DEFAULT 'draft',
    -- Snapshot der firmenweiten InvoiceSettings zum Zeitpunkt des Versendens
    -- (bleiben NULL, solange die Rechnung noch Entwurf ist), damit eine
    -- spätere Änderung der Steuereinstellungen bereits versendete
    -- Rechnungen nie rückwirkend verändert.
    is_small_business   TINYINT(1)     NULL,
    vat_rate_percent    DECIMAL(5, 2)  NULL,
    paid_at             DATETIME       NULL,
    note                TEXT           NULL,
    source_offer_id     CHAR(36)       NULL,
    source_rental_id    CHAR(36)       NULL,
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
    INDEX idx_invoices_customer (customer_id),
    INDEX idx_invoices_venue (venue_id),
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_dates (start_date, end_date),
    INDEX idx_invoices_number (invoice_number)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Positionen einer Rechnung, strukturell identisch zu offer_items
-- (Migration 021).
-- ---------------------------------------------------------------------
CREATE TABLE invoice_items (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    invoice_id          CHAR(36)      NOT NULL,
    article_id          CHAR(36)      NOT NULL,
    quantity            INT UNSIGNED  NOT NULL,
    unit_price_per_day  DECIMAL(10, 2) NOT NULL,
    line_note           VARCHAR(255)  NULL,
    CONSTRAINT fk_invoice_items_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_invoice_items_invoice (invoice_id),
    INDEX idx_invoice_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
