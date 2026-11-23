-- Driver's logbook, part 3: QR capture and odometer check.
--
--  - vehicles.logbook_qr_token: random token of the QR code (URL /fahrt/<token>).
--    It is stored as is so the QR code can be printed again at any time; a
--    new token invalidates printed codes. NULL = no QR code generated yet.
--  - logbook_attempts: counters of the public capture pages per vehicle and
--    client (key = SHA-256 of scope, vehicle and IP address):
--    scope 'odometer' counts wrong odometer readings (locked_until after 5
--    failures for 15 minutes), scope 'submit' counts trips of external
--    drivers per hour (rate limit).
ALTER TABLE vehicles
    ADD COLUMN logbook_qr_token VARCHAR(40) NULL AFTER logbook_odometer_initial_km,
    ADD UNIQUE KEY uq_vehicles_logbook_qr_token (logbook_qr_token);

CREATE TABLE logbook_attempts (
    attempt_key  CHAR(64)          NOT NULL PRIMARY KEY,
    scope        VARCHAR(10)       NOT NULL,
    counter      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME          NOT NULL,
    locked_until DATETIME          NULL,
    updated_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_logbook_attempts_updated (updated_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
