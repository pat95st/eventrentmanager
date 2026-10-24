-- Vermietbedingungen-Unterschrift (iPad) & Ausweisprüfung bei Neukunden
-- siehe architektur-und-setup.md, Abschnitt
-- "Vermietbedingungen-Unterschrift (iPad) & Ausweisprüfung bei Neukunden".

ALTER TABLE rentals
    ADD COLUMN terms_signed_at DATETIME NULL AFTER deployment_days,
    ADD COLUMN terms_signature_file VARCHAR(255) NULL AFTER terms_signed_at;

ALTER TABLE customers
    ADD COLUMN id_checked_at DATETIME NULL AFTER telegram_chat_id;
