-- Erlaubt eine über die Oberfläche konfigurierbare Preisstaffelung mit mehr
-- als 7 Einsatztagen (siehe App\Domain\Settings\PriceTier::MAX_DAY_COUNT).
ALTER TABLE price_tiers
    DROP CONSTRAINT chk_price_tiers_day_count,
    ADD CONSTRAINT chk_price_tiers_day_count CHECK (day_count BETWEEN 1 AND 60);
