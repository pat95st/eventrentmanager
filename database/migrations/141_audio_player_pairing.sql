-- Migration 141: Abspielgerät per 6-stelligem Code koppeln
--  - audio_pairing_codes: kurzlebige Kopplungs-Codes je Veranstaltung. Der Code
--    wird in der Veranstaltung unter "Audio & Durchsagen" erzeugt und am
--    Abspielgerät (AutoEventPlay) eingegeben. Das Gerät meldet sich damit an
--    POST /api/player/v1/pair, erhält seinen Geräte-Token und wird der
--    Veranstaltung zugeordnet.
--  - audio_pairing_failures: Fehlversuche je IP (Schutz gegen Durchprobieren)

CREATE TABLE audio_pairing_codes (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id       CHAR(36)     NOT NULL,
    code            CHAR(6)      NOT NULL,
    created_by_name VARCHAR(160) NOT NULL,
    created_at      DATETIME     NOT NULL,
    expires_at      DATETIME     NOT NULL,
    used_at         DATETIME     NULL,
    player_id       CHAR(36)     NULL,
    KEY idx_audio_pairing_codes_code (code, expires_at),
    KEY idx_audio_pairing_codes_rental (rental_id),
    CONSTRAINT fk_audio_pairing_codes_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE audio_pairing_failures (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45)     NOT NULL,
    attempted_at DATETIME        NOT NULL,
    KEY idx_audio_pairing_failures_ip (ip, attempted_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
