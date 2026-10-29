-- Nutzerwunsch "mehrere Bankverbindungen hinterlegen": bisher gab es genau
-- eine Bankverbindung in invoice_settings (bank_name/iban/bic). Neu: eigene
-- Tabelle mit beliebig vielen Konten, genau eines davon ist das
-- Standardkonto (is_default). Je Rechnung kann ein anderes Konto gewählt
-- werden (invoices.bank_account_id, NULL = Standardkonto zum
-- Zeitpunkt der Anzeige; beim Versenden wird das Standardkonto fest
-- eingetragen, siehe MarkInvoiceSentHandler).
--
-- Die bisherige Bankverbindung aus invoice_settings wird als Standardkonto
-- übernommen. Die alten Spalten bleiben vorerst bestehen (nur noch Fallback
-- für Installationen ohne Konto, siehe InvoicePdfGenerator::buildBankLine()).

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

INSERT INTO bank_accounts (id, label, account_holder, bank_name, iban, bic, is_default, created_at, updated_at)
SELECT UUID(), COALESCE(NULLIF(TRIM(bank_name), ''), 'Hauptkonto'), NULL, NULLIF(TRIM(bank_name), ''),
       REPLACE(UPPER(TRIM(iban)), ' ', ''), NULLIF(REPLACE(UPPER(TRIM(bic)), ' ', ''), ''), 1, NOW(), NOW()
FROM invoice_settings
WHERE id = 1 AND iban IS NOT NULL AND TRIM(iban) <> '';

ALTER TABLE invoices
    ADD COLUMN bank_account_id CHAR(36) NULL AFTER contact_id,
    ADD CONSTRAINT fk_invoices_bank_account
        FOREIGN KEY (bank_account_id) REFERENCES bank_accounts (id) ON DELETE SET NULL;
