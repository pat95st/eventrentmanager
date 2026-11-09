-- Migration 135 (Nutzerwunsch): Veranstaltungs-Audio
--  - rental_audio_files: Jingles und weitere Audio-Dateien je Veranstaltung
--    (WAV/FLAC/AAC/MP3); minute_programs.jingle_audio_file_id wählt je
--    Minutenprogramm den Jingle
--  - audio_players: Abspielgeräte (Raspberry Pi am Mischpult) mit Geräte-Token
--  - rental_audio_settings: zugeordnetes Abspielgerät + Gong-Vorgabe je Veranstaltung
--  - rental_organizer_logins: zeitlich begrenzbare Veranstalter-Zugänge zur Live-Oberfläche
--  - rental_playback_requests: Warteschlange (Jingle, Audio, Text-Durchsage,
--    Mikrofon-Aufnahme, Stopp), die der Pi abholt
--  - audio_settings: Gong-Datei, Sprachausgabe (Sprache/Stimme/Tempo), Upload-Limit

CREATE TABLE rental_audio_files (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id         CHAR(36)     NOT NULL,
    kind              VARCHAR(20)  NOT NULL,
    title             VARCHAR(160) NOT NULL,
    description       VARCHAR(500) NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename   VARCHAR(255) NOT NULL,
    mime_type         VARCHAR(100) NOT NULL,
    size_bytes        INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order        INT          NOT NULL DEFAULT 0,
    created_at        DATETIME     NOT NULL,
    KEY idx_rental_audio_files_rental (rental_id, kind, sort_order),
    CONSTRAINT fk_rental_audio_files_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE minute_programs
    ADD COLUMN jingle_audio_file_id CHAR(36) NULL AFTER jingle_before;

CREATE TABLE audio_players (
    id             CHAR(36)     NOT NULL PRIMARY KEY,
    name           VARCHAR(120) NOT NULL,
    token_hash     CHAR(64)     NOT NULL,
    token_hint     VARCHAR(20)  NOT NULL,
    enabled        TINYINT(1)   NOT NULL DEFAULT 1,
    last_seen_at   DATETIME     NULL,
    last_ip        VARCHAR(45)  NULL,
    player_version VARCHAR(60)  NULL,
    now_playing    VARCHAR(255) NULL,
    created_at     DATETIME     NOT NULL,
    UNIQUE KEY uq_audio_players_token (token_hash)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_audio_settings (
    rental_id        CHAR(36)   NOT NULL PRIMARY KEY,
    player_id        CHAR(36)   NULL,
    gong_by_default  TINYINT(1) NOT NULL DEFAULT 1,
    updated_at       DATETIME   NOT NULL,
    KEY idx_rental_audio_settings_player (player_id),
    CONSTRAINT fk_rental_audio_settings_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_audio_settings_player FOREIGN KEY (player_id) REFERENCES audio_players (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_organizer_logins (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id       CHAR(36)     NOT NULL,
    label           VARCHAR(120) NOT NULL,
    username        VARCHAR(80)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    valid_from      DATETIME     NULL,
    valid_until     DATETIME     NULL,
    enabled         TINYINT(1)   NOT NULL DEFAULT 1,
    failed_attempts INT          NOT NULL DEFAULT 0,
    locked_until    DATETIME     NULL,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL,
    UNIQUE KEY uq_rental_organizer_logins_username (username),
    KEY idx_rental_organizer_logins_rental (rental_id),
    CONSTRAINT fk_rental_organizer_logins_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_playback_requests (
    id                        CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id                 CHAR(36)     NOT NULL,
    kind                      VARCHAR(20)  NOT NULL,
    audio_file_id             CHAR(36)     NULL,
    label                     VARCHAR(160) NULL,
    text                      TEXT         NULL,
    recording_stored_filename VARCHAR(255) NULL,
    recording_mime_type       VARCHAR(100) NULL,
    with_gong                 TINYINT(1)   NOT NULL DEFAULT 0,
    status                    VARCHAR(20)  NOT NULL DEFAULT 'queued',
    requested_by_type         VARCHAR(20)  NOT NULL,
    requested_by_id           CHAR(36)     NULL,
    requested_by_name         VARCHAR(160) NOT NULL,
    player_id                 CHAR(36)     NULL,
    error                     VARCHAR(500) NULL,
    created_at                DATETIME     NOT NULL,
    started_at                DATETIME     NULL,
    finished_at               DATETIME     NULL,
    KEY idx_rental_playback_requests_queue (rental_id, status, created_at),
    CONSTRAINT fk_rental_playback_requests_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE audio_settings (
    id                     TINYINT      NOT NULL PRIMARY KEY,
    gong_original_filename VARCHAR(255) NULL,
    gong_stored_filename   VARCHAR(255) NULL,
    gong_mime_type         VARCHAR(100) NULL,
    tts_language           VARCHAR(20)  NOT NULL DEFAULT 'de-DE',
    tts_voice              VARCHAR(100) NULL,
    tts_rate               DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    max_upload_mb          INT          NOT NULL DEFAULT 50,
    queue_expiry_minutes   INT          NOT NULL DEFAULT 10,
    updated_at             DATETIME     NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO audio_settings (id, tts_language, tts_rate, max_upload_mb, queue_expiry_minutes, updated_at)
VALUES (1, 'de-DE', 1.00, 50, 10, NOW());
