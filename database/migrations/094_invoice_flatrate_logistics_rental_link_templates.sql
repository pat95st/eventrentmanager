-- Rechnungen: Pauschalpreis für Mietpositionen, Veranstaltungs-Verknüpfung
-- (source_rental_id existierte bereits, war aber über das normale
-- Rechnungsformular nicht editierbar/anzeigbar), Abhol-/Rückgabetermin bzw.
-- Liefer-/Abholtermin bei Lieferung durch uns, sowie je Position ein
-- Rental/Sale-Unterscheidungsflag (für die "nur Mietpositionen"-Regel der
-- Pauschale, siehe App\Domain\Pricing\DocumentTotals-Klassendoc).
-- Zusätzlich: Textvorlagen für Schlusstexte mit Ordnerstruktur (analog
-- categories/Category, siehe App\Domain\Category\Category).

ALTER TABLE invoices
    ADD COLUMN flat_rate_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER accessory_flat_rate_percent,
    ADD COLUMN flat_rate_amount DECIMAL(10, 2) NULL AFTER flat_rate_enabled,
    ADD COLUMN logistics_method ENUM('pickup', 'delivery') NULL AFTER source_rental_id,
    ADD COLUMN logistics_date1 DATETIME NULL AFTER logistics_method,
    ADD COLUMN logistics_date2 DATETIME NULL AFTER logistics_date1;

ALTER TABLE invoice_items
    ADD COLUMN is_rental_item TINYINT(1) NOT NULL DEFAULT 1 AFTER line_note;

CREATE TABLE closing_text_template_folders (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    parent_id   CHAR(36)     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_closing_text_template_folders_parent
        FOREIGN KEY (parent_id) REFERENCES closing_text_template_folders (id) ON DELETE SET NULL,
    INDEX idx_closing_text_template_folders_parent (parent_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE closing_text_templates (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    folder_id   CHAR(36)     NULL,
    name        VARCHAR(255) NOT NULL,
    -- Bereits bereinigtes Rich-Text-HTML, identisches Format wie
    -- Invoice::$closingText (siehe App\Infrastructure\RichText\HtmlSanitizer).
    content     MEDIUMTEXT   NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_closing_text_templates_folder
        FOREIGN KEY (folder_id) REFERENCES closing_text_template_folders (id) ON DELETE SET NULL,
    INDEX idx_closing_text_templates_folder (folder_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
