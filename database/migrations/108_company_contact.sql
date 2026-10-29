-- Nutzerwunsch "Fußzeile auf den Dokumenten" (Firma | Telefon/E-Mail/
-- Steuernummer | Bankverbindung): Telefon und E-Mail der Firma gab es bisher
-- nirgends in den Einstellungen. Steuernummer/USt-IdNr. stehen bereits in
-- invoice_settings, die Bankverbindung in bank_accounts (Migration 105).

ALTER TABLE company_settings
    ADD COLUMN phone VARCHAR(64)  NULL AFTER country,
    ADD COLUMN email VARCHAR(255) NULL AFTER phone,
    ADD COLUMN website VARCHAR(255) NULL AFTER email;
