-- Migration für bereits bestehende Installationen:
--   1. venue_stages: Bühnen eines Veranstaltungsorts (ein Veranstaltungsort
--      kann mehrere haben, z. B. "Hauptbühne" und "Kleinkunstbühne") mit
--      Name, Abmessungen (Breite × Tiefe), Bühnenhöhe und Bauart (fest
--      installiert oder mobil). Alle Längen in Metern — sie bilden später die
--      maßstäbliche Grundlage für Bühnenpläne an Veranstaltungen.
--   2. venue_stage_power_distances: Entfernung (Meter) einer Bühne zu einem
--      Stromanschluss desselben Veranstaltungsorts. Nur tatsächlich
--      angegebene Entfernungen werden gespeichert (kein Datensatz = unbekannt).
USE materialverleih;

CREATE TABLE venue_stages (
    id          CHAR(36)      NOT NULL PRIMARY KEY,
    venue_id    CHAR(36)      NOT NULL,
    name        VARCHAR(150)  NOT NULL,
    width_m     DECIMAL(5,2)  NOT NULL,
    depth_m     DECIMAL(5,2)  NOT NULL,
    height_m    DECIMAL(5,2)  NOT NULL,
    stage_type  VARCHAR(10)   NOT NULL, -- fixed | mobile
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_venue_stages_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE CASCADE,
    INDEX idx_venue_stages_venue (venue_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE venue_stage_power_distances (
    stage_id             CHAR(36)      NOT NULL,
    power_connection_id  CHAR(36)      NOT NULL,
    distance_m           DECIMAL(6,2)  NOT NULL,
    PRIMARY KEY (stage_id, power_connection_id),
    CONSTRAINT fk_venue_stage_power_distances_stage
        FOREIGN KEY (stage_id) REFERENCES venue_stages (id) ON DELETE CASCADE,
    CONSTRAINT fk_venue_stage_power_distances_connection
        FOREIGN KEY (power_connection_id) REFERENCES venue_power_connections (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
