-- Migration für bereits bestehende Installationen: analog zu Migration 037
-- (invoices) erhält auch offers die Zubehörpauschale (Snapshot-Prinzip) und
-- den optionalen Kombirabatt — siehe App\Domain\Settings\PricingSettings /
-- App\Domain\Pricing\DocumentTotals.
USE materialverleih;

ALTER TABLE offers
    ADD COLUMN accessory_flat_rate_enabled TINYINT(1)    NOT NULL DEFAULT 1 AFTER note,
    ADD COLUMN accessory_flat_rate_percent DECIMAL(5, 2)  NULL     AFTER accessory_flat_rate_enabled,
    ADD COLUMN discount_type               ENUM('percent', 'fixed') NULL AFTER accessory_flat_rate_percent,
    ADD COLUMN discount_value              DECIMAL(10, 2) NULL     AFTER discount_type;
