-- Migration für bereits bestehende Installationen: legt eine Stammdaten-
-- Tabelle für Veranstaltungsorte (mit Adresse + optional geokodierten
-- Koordinaten für die Kartenanzeige) an und verknüpft sie optional mit
-- Vermietungen.
USE materialverleih;

CREATE TABLE venues (
    id              CHAR(36)       NOT NULL PRIMARY KEY,
    name            VARCHAR(255)   NOT NULL,
    street          VARCHAR(255)   NOT NULL,
    postal_code     VARCHAR(20)    NOT NULL,
    city            VARCHAR(150)   NOT NULL,
    country         VARCHAR(100)   NULL,
    notes           TEXT           NULL,
    latitude        DECIMAL(10, 7) NULL,
    longitude       DECIMAL(10, 7) NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_venues_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE rentals
    ADD COLUMN venue_id CHAR(36) NULL AFTER event_location,
    ADD CONSTRAINT fk_rentals_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    ADD INDEX idx_rentals_venue (venue_id);
