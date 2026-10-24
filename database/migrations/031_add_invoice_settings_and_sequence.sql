-- Migration für bereits bestehende Installationen: firmenweite Einstellungen
-- für die Rechnungsfunktion (siehe App\Domain\Settings\InvoiceSettings) —
-- Kleinunternehmer-Regelung (§ 19 UStG), Steuersatz, Steuernummer/USt-IdNr.,
-- Rechnungsnummern-Präfix, Zahlungsziel, Bankverbindung — sowie die separate
-- Sequenz-Tabelle für die fortlaufende, lückenlose Rechnungsnummer (siehe
-- App\Infrastructure\Persistence\Pdo\InvoiceNumberSequence). Eine echte
-- Rechnungsnummer wird NIE bei MAX(invoice_number)+1 ermittelt (Race
-- Conditions, Lücken durch gelöschte Entwürfe würden die Nummer
-- fälschlicherweise wiederverwenden), sondern ausschließlich über diese
-- Singleton-Zeile mit SELECT ... FOR UPDATE innerhalb einer Transaktion
-- atomar hochgezählt.
USE materialverleih;

CREATE TABLE invoice_settings (
    id                     TINYINT       NOT NULL PRIMARY KEY DEFAULT 1,
    is_small_business      TINYINT(1)    NOT NULL DEFAULT 0,
    vat_rate_percent       DECIMAL(5, 2) NOT NULL DEFAULT 19.00,
    tax_number             VARCHAR(64)   NULL,
    vat_id                 VARCHAR(32)   NULL,
    invoice_number_prefix  VARCHAR(16)   NOT NULL DEFAULT 'RE-',
    payment_term_days      INT UNSIGNED  NOT NULL DEFAULT 14,
    bank_name              VARCHAR(255)  NULL,
    iban                   VARCHAR(34)   NULL,
    bic                    VARCHAR(11)   NULL,
    updated_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_invoice_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO invoice_settings (id, is_small_business, vat_rate_percent, tax_number, vat_id, invoice_number_prefix, payment_term_days, bank_name, iban, bic)
VALUES (1, 0, 19.00, NULL, NULL, 'RE-', 14, NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- Sequenz-Zeile für die fortlaufende Rechnungsnummer. next_number ist die
-- NÄCHSTE zu vergebende Nummer (startet bei 1). Wird ausschließlich über
-- SELECT next_number FROM invoice_sequences WHERE id = 1 FOR UPDATE
-- innerhalb derselben Transaktion gelesen und anschließend per UPDATE
-- hochgezählt (siehe InvoiceNumberSequence::allocateNext()).
-- ---------------------------------------------------------------------
CREATE TABLE invoice_sequences (
    id           TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    next_number  INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT chk_invoice_sequences_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO invoice_sequences (id, next_number) VALUES (1, 1);
