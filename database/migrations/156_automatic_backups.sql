-- Migration 156: Automatische Sicherung auf Speicherziele.
--
-- Die unter Einstellungen → Sicherung erzeugte Backup-ZIP (Datenbank +
-- storage/uploads/) kann per Cronjob (bin/run-automatic-backup.php)
-- automatisch auf die bereits für die Dokumentablage verbundenen
-- Speicherziele (document_storage_targets) übertragen werden.
--
--  - backup_settings: Singleton-Zeile (aktiviert, Ablagepfad-Vorlage,
--    Rhythmus und Uhrzeit, aus denen die angezeigte Crontab-Zeile entsteht).
--  - backup_storage_targets: die ausgewählten Speicherziele.
--  - backup_runs: Protokoll jedes Sicherungslaufs (Cronjob oder manuell)
--    inkl. Ergebnis je Speicherziel (details, JSON).
CREATE TABLE IF NOT EXISTS backup_settings (
    id                TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    enabled           TINYINT(1)   NOT NULL DEFAULT 0,
    target_path       VARCHAR(500) NOT NULL DEFAULT 'Sicherungen/{year}/{filename}',
    schedule_interval VARCHAR(20)  NOT NULL DEFAULT 'daily',
    run_hour          TINYINT      NOT NULL DEFAULT 3,
    run_minute        TINYINT      NOT NULL DEFAULT 15,
    -- 1 = Montag … 7 = Sonntag (nur bei wöchentlicher Sicherung).
    run_weekday       TINYINT      NOT NULL DEFAULT 1,
    -- 1–28 (nur bei monatlicher Sicherung).
    run_month_day     TINYINT      NOT NULL DEFAULT 1,
    CONSTRAINT chk_backup_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT IGNORE INTO backup_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS backup_storage_targets (
    storage_target_id CHAR(36) NOT NULL PRIMARY KEY,
    CONSTRAINT fk_backup_storage_targets_target
        FOREIGN KEY (storage_target_id) REFERENCES document_storage_targets (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS backup_runs (
    id           CHAR(36)        NOT NULL PRIMARY KEY,
    run_trigger  VARCHAR(20)     NOT NULL,
    status       VARCHAR(20)     NOT NULL,
    file_name    VARCHAR(255)    NULL,
    file_size    BIGINT UNSIGNED NULL,
    message      VARCHAR(1000)   NULL,
    details      TEXT            NULL,
    started_at   DATETIME        NOT NULL,
    finished_at  DATETIME        NOT NULL,
    INDEX idx_backup_runs_started (started_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
