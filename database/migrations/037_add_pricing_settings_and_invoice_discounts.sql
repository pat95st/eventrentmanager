-- Migration für bereits bestehende Installationen: firmenweite
-- Preis-Einstellungen (siehe App\Domain\Settings\PricingSettings) —
-- aktuell nur die Zubehörpauschale (Prozentsatz), die automatisch als
-- eigener Posten auf JEDES neue Angebot/jede neue Rechnung aufgeschlagen
-- wird, dort aber pro Dokument abwählbar ist. Bewusst eine EIGENE
-- Singleton-Tabelle statt eines weiteren Feldes in invoice_settings, weil
-- die Pauschale auch für Angebote gilt (invoice_settings ist laut
-- Klassen-Doc ausdrücklich nur "für die Rechnungsfunktion").
--
-- Außerdem: neue Spalten auf invoices für
--   - die pro Rechnung gesnapshotete Zubehörpauschale (Snapshot-Prinzip
--     wie is_small_business/vat_rate_percent, siehe Invoice-Klassen-Doc),
--   - den optionalen, frei wählbaren Kombirabatt (Prozent ODER Festbetrag).
-- Siehe App\Domain\Pricing\DocumentTotals für die Berechnungsreihenfolge
-- (Mietpreise -> Zubehörpauschale -> Kombirabatt -> Netto/USt/Brutto).
-- Die analogen Spalten für offers folgen in Migration 038.
USE materialverleih;

CREATE TABLE pricing_settings (
    id                           TINYINT       NOT NULL PRIMARY KEY DEFAULT 1,
    accessory_flat_rate_percent  DECIMAL(5, 2) NOT NULL DEFAULT 0,
    updated_at                   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_pricing_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO pricing_settings (id, accessory_flat_rate_percent) VALUES (1, 0);

ALTER TABLE invoices
    ADD COLUMN accessory_flat_rate_enabled TINYINT(1)    NOT NULL DEFAULT 1 AFTER vat_rate_percent,
    ADD COLUMN accessory_flat_rate_percent DECIMAL(5, 2)  NULL     AFTER accessory_flat_rate_enabled,
    ADD COLUMN discount_type               ENUM('percent', 'fixed') NULL AFTER accessory_flat_rate_percent,
    ADD COLUMN discount_value              DECIMAL(10, 2) NULL     AFTER discount_type;
