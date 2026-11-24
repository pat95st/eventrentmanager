-- Driver's logbook, part 2: trips.
--
--  - logbook_settings: edit_window_days = how long drivers may edit or
--    delete their own trips (logbook managers always can), default 7.
--  - logbook_entries: one trip. category 'event' (rental_id required,
--    tour_stop_id optional for tours), 'business' (purpose required),
--    'private' or 'unclear' (gap in the odometer chain, created by the
--    odometer check). Start and destination are stored as a snapshot
--    (from_*/to_*): type warehouse | event_venue | tour_stop | company |
--    saved_address | venue | custom, the referenced id, a label, the
--    address and coordinates at the time of saving, so the trip does not
--    change when master data changes later. distance_km is the whole trip
--    (doubled for round_trip); with both odometer readings it is end - start.
--    Drivers are users (driver_user_id) or external people (driver_name,
--    is_external). status 'approved' | 'pending' | 'rejected' (approval of
--    external trips). not_billed is set by logbook managers only.
--    Deleting is a soft delete (deleted_at).
--  - logbook_entry_changes: audit trail (created/updated/deleted/restored)
--    with the changed fields as JSON {field: [old, new]}.
CREATE TABLE logbook_settings (
    id               TINYINT           NOT NULL PRIMARY KEY DEFAULT 1,
    edit_window_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
    updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_logbook_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO logbook_settings (id) VALUES (1);

CREATE TABLE logbook_entries (
    id                      CHAR(36)       NOT NULL PRIMARY KEY,
    trip_date               DATE           NOT NULL,
    depart_time             TIME           NULL,
    arrive_time             TIME           NULL,
    driver_user_id          CHAR(36)       NULL,
    driver_name             VARCHAR(150)   NULL,
    driver_phone            VARCHAR(50)    NULL,
    is_external             TINYINT(1)     NOT NULL DEFAULT 0,
    vehicle_id              CHAR(36)       NULL,
    other_vehicle_label     VARCHAR(150)   NULL,
    trailer_id              CHAR(36)       NULL,
    category                VARCHAR(20)    NOT NULL,
    rental_id               CHAR(36)       NULL,
    tour_stop_id            CHAR(36)       NULL,
    purpose                 VARCHAR(500)   NULL,
    from_type               VARCHAR(20)    NULL,
    from_ref_id             CHAR(36)       NULL,
    from_label              VARCHAR(255)   NULL,
    from_address            VARCHAR(500)   NULL,
    from_latitude           DECIMAL(10, 7) NULL,
    from_longitude          DECIMAL(10, 7) NULL,
    to_type                 VARCHAR(20)    NULL,
    to_ref_id               CHAR(36)       NULL,
    to_label                VARCHAR(255)   NULL,
    to_address              VARCHAR(500)   NULL,
    to_latitude             DECIMAL(10, 7) NULL,
    to_longitude            DECIMAL(10, 7) NULL,
    round_trip              TINYINT(1)     NOT NULL DEFAULT 0,
    distance_km             INT UNSIGNED   NOT NULL,
    odometer_start          INT UNSIGNED   NULL,
    odometer_end            INT UNSIGNED   NULL,
    not_billed              TINYINT(1)     NOT NULL DEFAULT 0,
    status                  VARCHAR(10)    NOT NULL DEFAULT 'approved',
    odometer_check_bypassed TINYINT(1)     NOT NULL DEFAULT 0,
    created_by_user_id      CHAR(36)       NULL,
    created_at              DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              DATETIME       NULL,
    deleted_by_user_id      CHAR(36)       NULL,
    CONSTRAINT fk_logbook_entries_driver FOREIGN KEY (driver_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_logbook_entries_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_logbook_entries_trailer FOREIGN KEY (trailer_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_logbook_entries_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE SET NULL,
    CONSTRAINT fk_logbook_entries_tour_stop FOREIGN KEY (tour_stop_id) REFERENCES rental_tour_stops (id) ON DELETE SET NULL,
    CONSTRAINT fk_logbook_entries_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_logbook_entries_deleted_by FOREIGN KEY (deleted_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_logbook_entries_date (trip_date),
    INDEX idx_logbook_entries_vehicle (vehicle_id, trip_date),
    INDEX idx_logbook_entries_rental (rental_id),
    INDEX idx_logbook_entries_driver (driver_user_id, trip_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE logbook_entry_changes (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    entry_id   CHAR(36)     NOT NULL,
    user_id    CHAR(36)     NULL,
    actor_name VARCHAR(150) NULL,
    action     VARCHAR(10)  NOT NULL,
    changes    MEDIUMTEXT   NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logbook_entry_changes_entry FOREIGN KEY (entry_id) REFERENCES logbook_entries (id) ON DELETE CASCADE,
    CONSTRAINT fk_logbook_entry_changes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_logbook_entry_changes_entry (entry_id, created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
