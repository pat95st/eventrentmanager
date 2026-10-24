-- Migration für bereits bestehende Installationen:
--   1. venue_power_connections: Stromanschlüsse eines Veranstaltungsorts
--      (ein Veranstaltungsort kann mehrere haben — Ort/Bühne/Technikraum
--      jeweils mit eigener Anschlussart, Leitungsschutzschalter-Größe und
--      RCD-Auslösestrom). Die elektrotechnischen Warnungen (Leitungsschutz-
--      schalter größer als vom Anschlusstyp zulässig; eigener Verteiler mit
--      RCD nötig) werden NICHT gespeichert, sondern bei jeder Anzeige live
--      aus diesen drei Werten neu berechnet, siehe
--      App\Domain\Venue\PowerConnection.
--   2. caretaker_name/caretaker_phone an venues: optionale Kontaktdaten des
--      Hausmeisters vor Ort — beide NULLABLE, es besteht ausdrücklich KEINE
--      Pflicht, sie zu pflegen.
USE materialverleih;

CREATE TABLE venue_power_connections (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    venue_id          CHAR(36)     NOT NULL,
    connection_type   VARCHAR(50)  NOT NULL,
    breaker_rating    VARCHAR(20)  NOT NULL,
    rcd_trip_current  VARCHAR(30)  NOT NULL,
    location          VARCHAR(255) NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_venue_power_connections_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE CASCADE,
    INDEX idx_venue_power_connections_venue (venue_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE venues
    ADD COLUMN caretaker_name  VARCHAR(150) NULL AFTER notes,
    ADD COLUMN caretaker_phone VARCHAR(50)  NULL AFTER caretaker_name;
