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
