-- Migration für bereits bestehende Installationen: optionaler admin-
-- bearbeitbarer Einleitungs- und Schlusstext im eigentlichen Angebots-PDF-
-- Dokument selbst (siehe App\Infrastructure\Pdf\OfferPdfGenerator). Getrennt
-- von der bestehenden "Angebot per E-Mail"-Vorlage (email_templates,
-- Schlüssel offer_pdf) — dort geht es um den Text der E-Mail, hier um den
-- Text im PDF-Anhang. Beide Spalten sind NULL, solange kein Administrator
-- etwas hinterlegt hat, sodass das PDF unverändert wie bisher aussieht.
USE materialverleih;

CREATE TABLE offer_pdf_settings (
    id             TINYINT   NOT NULL PRIMARY KEY DEFAULT 1,
    intro_markdown MEDIUMTEXT NULL,
    closing_markdown MEDIUMTEXT NULL,
    updated_at     DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_offer_pdf_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO offer_pdf_settings (id, intro_markdown, closing_markdown) VALUES (1, NULL, NULL);
