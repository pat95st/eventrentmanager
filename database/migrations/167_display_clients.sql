-- Migration 167: Stage and FOH displays.
--
--  display_clients: display devices (stage monitor, confidence monitor,
--    FOH screen) that authenticate with a device token at the display API
--    (/api/display/v1, see docs/display-api.md). The role decides which
--    layout the client shows; it can be switched at any time so the same
--    device can serve as stage or FOH display. A device shows the minute
--    program it is pinned to, otherwise the current program of the assigned
--    event (or of all events running today), optionally limited to a room.
--    Layout options (notes per trade, clock, item list, control) are stored
--    as JSON in options_json.
--  display_settings: global defaults (single row id = 1): poll interval and
--    countdown warning thresholds.

CREATE TABLE display_clients (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    name              VARCHAR(120) NOT NULL,
    role              VARCHAR(20)  NOT NULL DEFAULT 'stage',
    token_hash        CHAR(64)     NOT NULL,
    token_hint        VARCHAR(20)  NOT NULL,
    enabled           TINYINT(1)   NOT NULL DEFAULT 1,
    rental_id         CHAR(36)     NULL,
    minute_program_id CHAR(36)     NULL,
    room              VARCHAR(120) NULL,
    options_json      TEXT         NULL,
    last_seen_at      DATETIME     NULL,
    last_ip           VARCHAR(45)  NULL,
    client_version    VARCHAR(60)  NULL,
    created_at        DATETIME     NOT NULL,
    UNIQUE KEY uq_display_clients_token (token_hash),
    KEY idx_display_clients_rental (rental_id),
    KEY idx_display_clients_program (minute_program_id),
    CONSTRAINT fk_display_clients_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE SET NULL,
    CONSTRAINT fk_display_clients_program FOREIGN KEY (minute_program_id) REFERENCES minute_programs (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE display_settings (
    id                    TINYINT      NOT NULL PRIMARY KEY,
    poll_interval_seconds INT          NOT NULL DEFAULT 3,
    warn_seconds          INT          NOT NULL DEFAULT 120,
    critical_seconds      INT          NOT NULL DEFAULT 30,
    updated_at            DATETIME     NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
