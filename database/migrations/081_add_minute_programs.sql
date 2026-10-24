-- Minutenprogramme (Ablaufpläne je Veranstaltung), siehe
-- architektur-und-setup.md, Abschnitt "Minutenprogramme".

CREATE TABLE minute_programs (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id               CHAR(36)     NOT NULL,
    name                    VARCHAR(255) NOT NULL,
    room                    VARCHAR(255) NULL,
    start_at                DATETIME     NOT NULL,
    jingle_before           TINYINT(1)   NOT NULL DEFAULT 0,
    handheld_mic_count      INT UNSIGNED NOT NULL DEFAULT 0,
    headset_mic_count       INT UNSIGNED NOT NULL DEFAULT 0,
    current_delay_minutes   INT          NOT NULL DEFAULT 0,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_minute_programs_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    INDEX idx_minute_programs_rental (rental_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE minute_program_items (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    minute_program_id   CHAR(36)     NOT NULL,
    sort_order          INT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL,
    description         TEXT         NULL,
    note_general        TEXT         NULL,
    note_sound          TEXT         NULL,
    note_light          TEXT         NULL,
    note_video          TEXT         NULL,
    note_stage          TEXT         NULL,
    duration_minutes    INT UNSIGNED NULL,
    CONSTRAINT fk_minute_program_items_program
        FOREIGN KEY (minute_program_id) REFERENCES minute_programs (id) ON DELETE CASCADE,
    INDEX idx_minute_program_items_program (minute_program_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE minute_program_templates (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    name                    VARCHAR(255) NOT NULL,
    jingle_before           TINYINT(1)   NOT NULL DEFAULT 0,
    handheld_mic_count      INT UNSIGNED NOT NULL DEFAULT 0,
    headset_mic_count       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE minute_program_template_items (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    template_id     CHAR(36)     NOT NULL,
    sort_order      INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    description     TEXT         NULL,
    note_general    TEXT         NULL,
    note_sound      TEXT         NULL,
    note_light      TEXT         NULL,
    note_video      TEXT         NULL,
    note_stage      TEXT         NULL,
    duration_minutes INT UNSIGNED NULL,
    CONSTRAINT fk_minute_program_template_items_template
        FOREIGN KEY (template_id) REFERENCES minute_program_templates (id) ON DELETE CASCADE,
    INDEX idx_minute_program_template_items_template (template_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE minute_program_share_links (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    minute_program_id   CHAR(36)     NOT NULL,
    token               VARCHAR(64)  NOT NULL,
    mode                ENUM('read', 'read_write') NOT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at          DATETIME     NULL,
    CONSTRAINT fk_minute_program_share_links_program
        FOREIGN KEY (minute_program_id) REFERENCES minute_programs (id) ON DELETE CASCADE,
    UNIQUE KEY uq_minute_program_share_links_token (token),
    INDEX idx_minute_program_share_links_program (minute_program_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
