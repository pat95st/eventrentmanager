-- Migration 127: Sicherheits-Einstellungen (Singleton-Zeile), zunächst die
-- automatische Abmeldung nach Inaktivität (Session-Timeout) in Minuten.
-- Nur Administratoren können den Wert unter Einstellungen › Sicherheit ändern.
-- Standard 120 Minuten.

CREATE TABLE security_settings (
    id                      TINYINT           NOT NULL PRIMARY KEY DEFAULT 1,
    session_timeout_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 120,
    CONSTRAINT chk_security_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO security_settings (id, session_timeout_minutes) VALUES (1, 120);
