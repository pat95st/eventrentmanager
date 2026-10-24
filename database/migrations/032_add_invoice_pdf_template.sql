-- Migration für bereits bestehende Installationen: optionaler admin-
-- bearbeitbarer Einleitungs- und Schlusstext im eigentlichen Rechnungs-PDF-
-- Dokument selbst (siehe App\Infrastructure\Pdf\InvoicePdfGenerator),
-- strukturell identisch zu offer_pdf_settings (Migration 030). Getrennt von
-- der "Rechnung per E-Mail"-Vorlage (email_templates, Schlüssel
-- invoice_pdf) — dort geht es um den Text der E-Mail, hier um den Text im
-- PDF-Anhang. Beide Spalten sind NULL, solange kein Administrator etwas
-- hinterlegt hat.
USE materialverleih;

CREATE TABLE invoice_pdf_settings (
    id                TINYINT    NOT NULL PRIMARY KEY DEFAULT 1,
    intro_markdown    MEDIUMTEXT NULL,
    closing_markdown  MEDIUMTEXT NULL,
    updated_at        DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_invoice_pdf_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO invoice_pdf_settings (id, intro_markdown, closing_markdown) VALUES (1, NULL, NULL);
