-- Migration 167: Messages from the organizer to the stage display and FOH.
--
--  - event_messages: short text messages sent from the live page
--    ("Audio & Durchsagen", /live/{id}) to the stage display (run sheet API,
--    docs/stagedisplay-api.md) and/or the FOH run sheet page.
--    minute_program_id NULL = all minute programs of the rental.
--    A message stays active until it is closed (withdrawn by the sender or
--    hidden at FOH); acknowledged_at records that FOH has seen it.

CREATE TABLE IF NOT EXISTS event_messages (
    id CHAR(36) NOT NULL PRIMARY KEY,
    rental_id CHAR(36) NOT NULL,
    minute_program_id CHAR(36) NULL,
    body VARCHAR(500) NOT NULL,
    to_stage TINYINT(1) NOT NULL DEFAULT 0,
    to_foh TINYINT(1) NOT NULL DEFAULT 0,
    urgent TINYINT(1) NOT NULL DEFAULT 0,
    sent_by_type VARCHAR(20) NOT NULL,
    sent_by_id CHAR(36) NULL,
    sent_by_name VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL,
    acknowledged_at DATETIME NULL,
    acknowledged_by VARCHAR(160) NULL,
    closed_at DATETIME NULL,
    closed_by VARCHAR(160) NULL,
    KEY idx_event_messages_rental (rental_id, closed_at, created_at),
    CONSTRAINT fk_event_messages_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_messages_program FOREIGN KEY (minute_program_id) REFERENCES minute_programs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
