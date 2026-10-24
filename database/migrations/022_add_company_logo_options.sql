-- Migration für bereits bestehende Installationen: konfigurierbare maximale
-- Logo-Höhe im Header sowie ein optionales zweites Logo speziell für den
-- Dunkelmodus (Oberfläche, nicht PDFs — dort wird immer das Hell-Logo
-- verwendet, siehe PackingListPdfGenerator/OfferPdfGenerator).
USE materialverleih;

ALTER TABLE company_settings
    ADD COLUMN logo_max_height_px SMALLINT NULL AFTER logo_filename,
    ADD COLUMN dark_logo_filename VARCHAR(255) NULL AFTER logo_max_height_px,
    ADD CONSTRAINT chk_company_settings_logo_max_height_px
        CHECK (logo_max_height_px IS NULL OR (logo_max_height_px BETWEEN 16 AND 200));
