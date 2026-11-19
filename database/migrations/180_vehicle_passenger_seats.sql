-- Passenger seats of a vehicle (excluding the driver), 0..8.
--
--  - vehicles.passenger_seats: only relevant for category = 'auto'; NULL =
--    not specified, always NULL for trailers. The application enforces the
--    range and the category rule (see ValidatesVehicleInput and
--    Vehicle::MAX_PASSENGER_SEATS), not the database — analogous to
--    tow_capacity_kg/empty_weight_kg.
ALTER TABLE vehicles
    ADD COLUMN passenger_seats TINYINT UNSIGNED NULL AFTER gross_weight_kg;
