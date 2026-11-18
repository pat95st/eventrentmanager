-- Tours: one rental with several stops at different places and times
-- (rentals.is_tour). The material of rental_items is the base list of the
-- tour; per stop it can deviate (rental_tour_stop_items), and the crew can
-- deviate per stop as well (rental_tour_stop_technicians).
--
--  - rental_tour_stops.kind: 'stop' (event at a venue) or 'waypoint'
--    (stop between venues: warehouse, company address, saved or custom
--    address; purpose load/overnight/break/other). A waypoint sits after
--    the stop after_stop_id (NULL = before the first stop).
--  - rental_tour_stop_items.delta: + additional / - fewer pieces at this
--    stop compared to the base list. allocation 'whole_tour' = travels the
--    whole tour (occupied for the whole period), 'stops_only' = occupied only
--    around the affected stops; issue_waypoint_id/return_waypoint_id name an
--    optional warehouse waypoint where it is loaded/unloaded.
--  - rental_items.tour_extra_quantity: pieces that may be issued on top of
--    the base quantity because stops need more (maintained by the tour code).
--  - rental_tour_stop_technicians.action: 'replace' (user_id replaces
--    replaces_user_id), 'add' or 'remove' (user_id) for this stop.
--  - minute_programs.tour_stop_id: minute program of a single stop.
ALTER TABLE rentals
    ADD COLUMN is_tour TINYINT(1) NOT NULL DEFAULT 0 AFTER series_customized;

ALTER TABLE rental_items
    ADD COLUMN tour_extra_quantity INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity;

CREATE TABLE tour_saved_addresses (
    id          CHAR(36)       NOT NULL PRIMARY KEY,
    label       VARCHAR(255)   NOT NULL,
    street      VARCHAR(255)   NOT NULL,
    postal_code VARCHAR(20)    NOT NULL,
    city        VARCHAR(150)   NOT NULL,
    country     VARCHAR(100)   NULL,
    latitude    DECIMAL(10, 7) NULL,
    longitude   DECIMAL(10, 7) NULL,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tour_saved_addresses_label (label)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_tour_stops (
    id               CHAR(36)       NOT NULL PRIMARY KEY,
    rental_id        CHAR(36)       NOT NULL,
    kind             VARCHAR(10)    NOT NULL,
    -- stop
    venue_id         CHAR(36)       NULL,
    location_text    VARCHAR(255)   NULL,
    setup_at         DATETIME       NULL,
    event_start_at   DATETIME       NULL,
    event_end_at     DATETIME       NULL,
    teardown_end_at  DATETIME       NULL,
    contact_name     VARCHAR(150)   NULL,
    contact_phone    VARCHAR(50)    NULL,
    note             TEXT           NULL,
    -- waypoint
    after_stop_id    CHAR(36)       NULL,
    purpose          VARCHAR(20)    NULL,
    address_type     VARCHAR(20)    NULL,
    warehouse_id     CHAR(36)       NULL,
    saved_address_id CHAR(36)       NULL,
    label            VARCHAR(255)   NULL,
    street           VARCHAR(255)   NULL,
    postal_code      VARCHAR(20)    NULL,
    city             VARCHAR(150)   NULL,
    country          VARCHAR(100)   NULL,
    latitude         DECIMAL(10, 7) NULL,
    longitude        DECIMAL(10, 7) NULL,
    arrive_at        DATETIME       NULL,
    depart_at        DATETIME       NULL,
    created_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_tour_stops_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_tour_stops_venue FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    CONSTRAINT fk_rental_tour_stops_after FOREIGN KEY (after_stop_id) REFERENCES rental_tour_stops (id) ON DELETE SET NULL,
    CONSTRAINT fk_rental_tour_stops_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    CONSTRAINT fk_rental_tour_stops_saved_address FOREIGN KEY (saved_address_id) REFERENCES tour_saved_addresses (id) ON DELETE SET NULL,
    INDEX idx_rental_tour_stops_rental (rental_id, event_start_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_tour_stop_items (
    id                 CHAR(36)    NOT NULL PRIMARY KEY,
    stop_id            CHAR(36)    NOT NULL,
    article_id         CHAR(36)    NOT NULL,
    delta              INT         NOT NULL,
    allocation         VARCHAR(20) NOT NULL DEFAULT 'stops_only',
    issue_waypoint_id  CHAR(36)    NULL,
    return_waypoint_id CHAR(36)    NULL,
    CONSTRAINT fk_rental_tour_stop_items_stop FOREIGN KEY (stop_id) REFERENCES rental_tour_stops (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_tour_stop_items_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_rental_tour_stop_items_issue FOREIGN KEY (issue_waypoint_id) REFERENCES rental_tour_stops (id) ON DELETE SET NULL,
    CONSTRAINT fk_rental_tour_stop_items_return FOREIGN KEY (return_waypoint_id) REFERENCES rental_tour_stops (id) ON DELETE SET NULL,
    UNIQUE KEY uq_rental_tour_stop_items (stop_id, article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_tour_stop_technicians (
    id               CHAR(36)    NOT NULL PRIMARY KEY,
    stop_id          CHAR(36)    NOT NULL,
    action           VARCHAR(10) NOT NULL,
    user_id          CHAR(36)    NOT NULL,
    replaces_user_id CHAR(36)    NULL,
    created_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_tour_stop_technicians_stop FOREIGN KEY (stop_id) REFERENCES rental_tour_stops (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_tour_stop_technicians_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_tour_stop_technicians_replaces FOREIGN KEY (replaces_user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_rental_tour_stop_technicians_stop (stop_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE minute_programs
    ADD COLUMN tour_stop_id CHAR(36) NULL AFTER rental_id,
    ADD CONSTRAINT fk_minute_programs_tour_stop
        FOREIGN KEY (tour_stop_id) REFERENCES rental_tour_stops (id) ON DELETE SET NULL;
