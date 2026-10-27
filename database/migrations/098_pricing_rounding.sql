-- Globale Rundungseinstellung für die aus der Preisstaffelung berechneten
-- Mietpreise (siehe App\Domain\Settings\RentalPriceCalculator,
-- PriceRoundingStep, PriceRoundingDirection). Beide Spalten NOT NULL mit
-- Default ('none'/'nearest'), damit sich das Verhalten bestehender
-- Installationen durch dieses Update NICHT automatisch ändert — Rundung
-- bleibt deaktiviert, bis sie unter Einstellungen > Preise explizit
-- aktiviert wird.

ALTER TABLE pricing_settings
    ADD COLUMN price_rounding_step VARCHAR(10) NOT NULL DEFAULT 'none' AFTER accessory_flat_rate_percent,
    ADD COLUMN price_rounding_direction VARCHAR(10) NOT NULL DEFAULT 'nearest' AFTER price_rounding_step;
