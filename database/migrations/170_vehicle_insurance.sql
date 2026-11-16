-- Migration 170: Vehicles on the insurance list of an event
--  - vehicles.acquisition_year/purchase_price/current_value: values that
--    are exported for a vehicle on the insurance list (all optional,
--    missing values stay empty in the export)
--  - rental_insurance_vehicles: vehicles explicitly put on the insurance
--    list of an event (independent of the loading list)

ALTER TABLE vehicles
    ADD COLUMN IF NOT EXISTS acquisition_year SMALLINT UNSIGNED NULL AFTER empty_weight_kg,
    ADD COLUMN IF NOT EXISTS purchase_price   DECIMAL(10,2)     NULL AFTER acquisition_year,
    ADD COLUMN IF NOT EXISTS current_value    DECIMAL(10,2)     NULL AFTER purchase_price;

CREATE TABLE IF NOT EXISTS rental_insurance_vehicles (
    rental_id   CHAR(36) NOT NULL,
    vehicle_id  CHAR(36) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rental_id, vehicle_id),
    CONSTRAINT fk_rental_insurance_vehicles_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_insurance_vehicles_vehicle
        FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE,
    INDEX idx_rental_insurance_vehicles_vehicle (vehicle_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
