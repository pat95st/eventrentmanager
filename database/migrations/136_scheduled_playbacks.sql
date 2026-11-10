-- Migration 136: zeitlich geplante Audio-Einspieler (Jingles / Audio-Dateien)
--  - scheduled_playbacks: geplante Wiedergaben je Veranstaltung; zur Startzeit
--    legt der Server daraus einen normalen Abspielauftrag an (beim Abruf durch
--    das Abspielgerät bzw. die Live-Oberfläche, kein Cronjob nötig)
--  - rental_organizer_logins.can_schedule: Veranstalter-Zugang darf planen
--    (inkl. eigener Audio-Uploads); muss beim Anlegen bewusst erlaubt werden

ALTER TABLE rental_organizer_logins
    ADD COLUMN can_schedule TINYINT(1) NOT NULL DEFAULT 0 AFTER enabled;

CREATE TABLE IF NOT EXISTS scheduled_playbacks (
    id CHAR(36) NOT NULL PRIMARY KEY,
    rental_id CHAR(36) NOT NULL,
    audio_file_id CHAR(36) NOT NULL,
    play_at DATETIME NOT NULL,
    note VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    playback_request_id CHAR(36) NULL,
    created_by_type VARCHAR(20) NOT NULL,
    created_by_id CHAR(36) NULL,
    created_by_name VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL,
    triggered_at DATETIME NULL,
    KEY idx_scheduled_playbacks_due (rental_id, status, play_at),
    CONSTRAINT fk_scheduled_playbacks_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_scheduled_playbacks_file FOREIGN KEY (audio_file_id) REFERENCES rental_audio_files (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
