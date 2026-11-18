-- Routes of tours (OpenRouteService), vehicle dimensions for truck routing
-- and planned vehicles of an event.
--
--  - routing_settings: API key of OpenRouteService and the buffer (minutes)
--    between teardown + driving time and the next setup.
--  - company_settings.latitude/longitude: geocoded company address (start,
--    destination or waypoint of a route).
--  - vehicles.height_cm/width_cm/length_cm/gross_weight_kg: dimensions and
--    permitted total weight for the truck profile, all optional.
--  - rental_planned_vehicles: vehicles planned for an event already before
--    issuing; towing_vehicle_id = car that pulls this trailer.
--  - rentals.route_profile: 'car', 'hgv' (truck without restrictions) or
--    'hgv_vehicles' (truck with the dimensions of the planned vehicles).
--  - rental_tour_stops.kind 'start'/'end': start and destination of a tour
--    route (same address fields as waypoints); without them the pickup and
--    return warehouses are used.
--  - rental_route_cache: last calculated route per event, keyed by a hash of
--    points, profile and restrictions, so the API is not called on every
--    page view.
CREATE TABLE routing_settings (
    id             TINYINT           NOT NULL PRIMARY KEY DEFAULT 1,
    api_key        VARCHAR(255)      NULL,
    buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    updated_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_routing_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO routing_settings (id) VALUES (1);

ALTER TABLE company_settings
    ADD COLUMN latitude  DECIMAL(10, 7) NULL,
    ADD COLUMN longitude DECIMAL(10, 7) NULL;

ALTER TABLE vehicles
    ADD COLUMN height_cm       DECIMAL(6, 1) NULL AFTER empty_weight_kg,
    ADD COLUMN width_cm        DECIMAL(6, 1) NULL AFTER height_cm,
    ADD COLUMN length_cm       DECIMAL(6, 1) NULL AFTER width_cm,
    ADD COLUMN gross_weight_kg DECIMAL(8, 2) NULL AFTER length_cm;

CREATE TABLE rental_planned_vehicles (
    id                CHAR(36) NOT NULL PRIMARY KEY,
    rental_id         CHAR(36) NOT NULL,
    vehicle_id        CHAR(36) NOT NULL,
    towing_vehicle_id CHAR(36) NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_planned_vehicles_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_planned_vehicles_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_planned_vehicles_towing FOREIGN KEY (towing_vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL,
    UNIQUE KEY uq_rental_planned_vehicles (rental_id, vehicle_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE rentals
    ADD COLUMN route_profile VARCHAR(20) NOT NULL DEFAULT 'car' AFTER is_tour;

CREATE TABLE rental_route_cache (
    rental_id  CHAR(36)   NOT NULL PRIMARY KEY,
    hash       CHAR(64)   NOT NULL,
    payload    MEDIUMTEXT NOT NULL,
    created_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_route_cache_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
