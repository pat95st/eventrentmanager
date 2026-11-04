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
