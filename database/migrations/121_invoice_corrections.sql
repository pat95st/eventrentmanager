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
