-- Nutzerwunsch "Zahlungsarten mit Zahlungszielen konfigurierbar": statt
-- eines einzigen, firmenweiten Zahlungsziels (invoice_settings.
-- payment_term_days) gibt es beliebig viele Zahlungsarten (z. B.
-- "Überweisung 14 Tage", "Bar bei Abholung", "Vorkasse") mit eigenem
-- Zahlungsziel und optionalem Hinweistext fürs Dokument; genau eine ist der
-- Standard. Auswählbar je Rechnung, Angebot und Auftragsbestätigung
-- (payment_method_id, NULL = Standard-Zahlungsart; beim Versenden einer
-- Rechnung wird die Zahlungsart fest eingetragen und das Fälligkeitsdatum
-- daraus berechnet, siehe MarkInvoiceSentHandler).
--
-- Das bisherige firmenweite Zahlungsziel wird als Standard-Zahlungsart
-- "Überweisung" übernommen; invoice_settings.payment_term_days bleibt nur
-- noch als Fallback bestehen.

CREATE TABLE payment_methods (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    name              VARCHAR(255) NOT NULL,
    payment_term_days INT          NOT NULL DEFAULT 14,
    note_text         TEXT         NULL,
    is_default        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO payment_methods (id, name, payment_term_days, note_text, is_default, created_at, updated_at)
SELECT UUID(), 'Überweisung', payment_term_days, NULL, 1, NOW(), NOW()
FROM invoice_settings
WHERE id = 1;

ALTER TABLE invoices
    ADD COLUMN payment_method_id CHAR(36) NULL AFTER bank_account_id,
    ADD CONSTRAINT fk_invoices_payment_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL;

ALTER TABLE offers
    ADD COLUMN payment_method_id CHAR(36) NULL AFTER contact_id,
    ADD CONSTRAINT fk_offers_payment_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL;

ALTER TABLE order_confirmations
    ADD COLUMN payment_method_id CHAR(36) NULL AFTER contact_id,
    ADD CONSTRAINT fk_order_confirmations_payment_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL;
