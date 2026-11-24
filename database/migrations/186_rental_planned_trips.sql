-- Driver's logbook, part 4: planned trips of events that are not tours.
--
--  - rental_planned_trips: planned trips of an event (tours keep their route,
--    migration 178). Start and destination are snapshots like in
--    logbook_entries (from_*/to_*). distance_km is the calculated driving
--    distance (OpenRouteService), km_override the kilometres entered by
--    hand (wins if set). default_kind 'outbound' (pickup warehouse → event)
--    or 'return' (event → return warehouse) marks an unchanged default trip;
--    it follows changes of the warehouses, location and dates of the event
--    (default_signature = state the trip was built from). Editing a trip
--    makes it a custom trip (default_kind NULL).
--  - rentals.planned_trips_initialized: the default trips were created once,
--    so deleting them does not bring them back automatically.
--  - vehicle_settings.event_travel_costs_default: "Fahrtkosten abrechnen" for
--    new events that are not tours (default off). Existing events that are
--    not tours get travel costs switched off; tours keep theirs.
ALTER TABLE vehicle_settings
    ADD COLUMN event_travel_costs_default TINYINT(1) NOT NULL DEFAULT 0 AFTER travel_cost_markup_percent;

ALTER TABLE rentals
    ADD COLUMN planned_trips_initialized TINYINT(1) NOT NULL DEFAULT 0 AFTER travel_costs_mode;

UPDATE rentals SET travel_costs_enabled = 0 WHERE is_tour = 0;

CREATE TABLE rental_planned_trips (
    id                CHAR(36)       NOT NULL PRIMARY KEY,
    rental_id         CHAR(36)       NOT NULL,
    sort_order        INT            NOT NULL DEFAULT 0,
    trip_date         DATE           NULL,
    from_type         VARCHAR(20)    NULL,
    from_ref_id       CHAR(36)       NULL,
    from_label        VARCHAR(255)   NULL,
    from_address      VARCHAR(500)   NULL,
    from_latitude     DECIMAL(10, 7) NULL,
    from_longitude    DECIMAL(10, 7) NULL,
    to_type           VARCHAR(20)    NULL,
    to_ref_id         CHAR(36)       NULL,
    to_label          VARCHAR(255)   NULL,
    to_address        VARCHAR(500)   NULL,
    to_latitude       DECIMAL(10, 7) NULL,
    to_longitude      DECIMAL(10, 7) NULL,
    distance_km       INT UNSIGNED   NULL,
    km_override       INT UNSIGNED   NULL,
    default_kind      VARCHAR(10)    NULL,
    default_signature VARCHAR(500)   NULL,
    created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_planned_trips_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    INDEX idx_rental_planned_trips_rental (rental_id, trip_date, sort_order)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
