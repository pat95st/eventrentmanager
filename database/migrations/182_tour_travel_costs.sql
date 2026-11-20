-- Travel costs of tours on offers, order confirmations and invoices.
--
--  - rentals.travel_costs_enabled: 1 = the kilometres of the tour are billed
--    (default for every tour). rentals.travel_costs_mode: 'summary' (one
--    line with all kilometres) or 'itemized' (one line per trip).
--  - rental_tour_stops.arrival_km_override: kilometres of the drive TO this
--    point entered by hand (replaces the calculated distance of the leg).
--  - vehicle_settings.travel_cost_markup_percent: markup on the cost per km
--    of the vehicles when travel costs are billed (default 0).
--  - document_travel_costs: travel cost section of exactly one document
--    (offer_id, order_confirmation_id or invoice_id). The data is copied
--    from the tour, so a sent document never changes afterwards. trips holds
--    the trips as JSON ([{date, from, to, km}]) to rebuild the lines when the
--    mode is switched; vehicles_label/route_label/rate_per_km are the values
--    at the time of the import.
--  - document_travel_cost_lines: the lines printed on the document (editable
--    while the document is a draft).
ALTER TABLE rentals
    ADD COLUMN travel_costs_enabled TINYINT(1)  NOT NULL DEFAULT 1 AFTER route_profile,
    ADD COLUMN travel_costs_mode    VARCHAR(10) NOT NULL DEFAULT 'summary' AFTER travel_costs_enabled;

ALTER TABLE rental_tour_stops
    ADD COLUMN arrival_km_override INT UNSIGNED NULL AFTER arrival_not_billed;

ALTER TABLE vehicle_settings
    ADD COLUMN travel_cost_markup_percent DECIMAL(5, 2) NOT NULL DEFAULT 0.00 AFTER default_cost_per_km_trailer;

CREATE TABLE document_travel_costs (
    id                    CHAR(36)      NOT NULL PRIMARY KEY,
    offer_id              CHAR(36)      NULL,
    order_confirmation_id CHAR(36)      NULL,
    invoice_id            CHAR(36)      NULL,
    source_rental_id      CHAR(36)      NULL,
    mode                  VARCHAR(10)   NOT NULL DEFAULT 'summary',
    vehicles_label        VARCHAR(255)  NULL,
    route_label           VARCHAR(1000) NULL,
    rate_per_km           DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    trips                 MEDIUMTEXT    NOT NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_document_travel_costs_offer FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE,
    CONSTRAINT fk_document_travel_costs_order_confirmation FOREIGN KEY (order_confirmation_id) REFERENCES order_confirmations (id) ON DELETE CASCADE,
    CONSTRAINT fk_document_travel_costs_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_document_travel_costs_rental FOREIGN KEY (source_rental_id) REFERENCES rentals (id) ON DELETE SET NULL,
    UNIQUE KEY uq_document_travel_costs_offer (offer_id),
    UNIQUE KEY uq_document_travel_costs_order_confirmation (order_confirmation_id),
    UNIQUE KEY uq_document_travel_costs_invoice (invoice_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE document_travel_cost_lines (
    id              CHAR(36)       NOT NULL PRIMARY KEY,
    travel_cost_id  CHAR(36)       NOT NULL,
    sort_order      INT            NOT NULL DEFAULT 0,
    trip_date       DATE           NULL,
    description     VARCHAR(255)   NOT NULL,
    detail          VARCHAR(1000)  NULL,
    kilometers      DECIMAL(10, 2) NOT NULL,
    rate_per_km     DECIMAL(10, 2) NOT NULL,
    CONSTRAINT fk_document_travel_cost_lines_section FOREIGN KEY (travel_cost_id) REFERENCES document_travel_costs (id) ON DELETE CASCADE,
    INDEX idx_document_travel_cost_lines_section (travel_cost_id, sort_order)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
