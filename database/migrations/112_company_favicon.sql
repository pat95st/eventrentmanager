-- Nutzerwunsch: eigenes Favicon zusätzlich zum Logo hochladen
-- (Einstellungen › Firma). Ohne Upload bleibt public/favicon.ico.

ALTER TABLE company_settings
    ADD COLUMN favicon_filename VARCHAR(255) NULL AFTER background_image_filename;
