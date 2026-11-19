-- Cost per kilometre of vehicles and trailers.
--
--  - vehicle_settings: default cost per km for cars (0.30 EUR) and trailers
--    (0.15 EUR), editable under Einstellungen › Fahrzeuge.
--  - vehicles.cost_per_km: individual cost per km of a vehicle; NULL means
--    the default of its category from vehicle_settings applies.
CREATE TABLE vehicle_settings (
    id                           TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    default_cost_per_km_auto     DECIMAL(6, 2) NOT NULL DEFAULT 0.30,
    default_cost_per_km_trailer  DECIMAL(6, 2) NOT NULL DEFAULT 0.15,
    updated_at                   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_vehicle_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO vehicle_settings (id) VALUES (1);

ALTER TABLE vehicles
    ADD COLUMN cost_per_km DECIMAL(6, 2) NULL AFTER passenger_seats;
