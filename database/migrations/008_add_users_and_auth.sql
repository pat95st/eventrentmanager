-- Migration 008: Benutzerverwaltung, Authentifizierung (Passwort + optionaler
-- zweiter Faktor per TOTP oder E-Mail-Code) sowie zentrale SMTP-Zugangsdaten.
--
-- Rechte werden als kommaseparierte Liste in users.permissions gespeichert
-- (z. B. "artikelverwaltung,kundenverwaltung"). Das Recht "administration"
-- schließt alle übrigen Rechte ein (Prüfung erfolgt im Code, siehe
-- App\Domain\User\Permission), wird hier aber bewusst nicht zusätzlich
-- expandiert gespeichert.

CREATE TABLE users (
    id                    CHAR(36)     NOT NULL PRIMARY KEY,
    email                 VARCHAR(255) NOT NULL,
    password_hash         VARCHAR(255) NOT NULL,
    -- Kommaseparierte Liste von Permission-Werten, siehe App\Domain\User\Permission.
    permissions           VARCHAR(255) NOT NULL DEFAULT '',
    two_factor_method     ENUM('none', 'totp', 'email') NOT NULL DEFAULT 'none',
    -- Base32-kodiertes TOTP-Secret, nur gesetzt wenn two_factor_method = 'totp'.
    totp_secret            VARCHAR(64)  NULL,
    must_change_password   TINYINT(1)   NOT NULL DEFAULT 0,
    is_active               TINYINT(1)   NOT NULL DEFAULT 1,
    created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Kurzlebige Challenges für den zweiten Faktor per E-Mail-Code: ein
-- 6-stelliger Code wird generiert, gehasht gespeichert und per SMTP
-- versendet; verifyTwoFactorCode() vergleicht den eingegebenen Code gegen
-- den Hash und prüft expires_at. TOTP braucht keine eigene Tabelle, da der
-- Code aus users.totp_secret + der aktuellen Uhrzeit berechnet wird.
-- ---------------------------------------------------------------------
CREATE TABLE login_challenges (
    id           CHAR(36)   NOT NULL PRIMARY KEY,
    user_id      CHAR(36)   NOT NULL,
    code_hash    VARCHAR(255) NOT NULL,
    expires_at   DATETIME   NOT NULL,
    consumed_at  DATETIME   NULL,
    created_at   DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_challenges_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_login_challenges_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Zentrale SMTP-Zugangsdaten für den Mailversand (2FA-E-Mail-Codes, ggf.
-- künftige Benachrichtigungen). Bewusst eine Singleton-Zeile (id = 1) statt
-- einer YAML-Datei wie bei den DB-Zugangsdaten, da SMTP-Zugangsdaten laut
-- Anforderung über eine für Administratoren sichtbare Oberfläche editierbar
-- sein sollen, nicht nur per Datei auf dem Server.
-- ---------------------------------------------------------------------
CREATE TABLE smtp_settings (
    id            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    host          VARCHAR(255) NOT NULL DEFAULT '',
    port          INT UNSIGNED NOT NULL DEFAULT 587,
    encryption    ENUM('none', 'ssl', 'tls') NOT NULL DEFAULT 'tls',
    username      VARCHAR(255) NULL,
    -- Kein eigenes Verschlüsselungsschema für das SMTP-Passwort: die Tabelle
    -- ist ohnehin nur für Administratoren über die Anwendung erreichbar,
    -- analog zu den bereits bestehenden DB-Zugangsdaten in database.yaml.
    password      VARCHAR(255) NULL,
    from_address  VARCHAR(255) NOT NULL DEFAULT '',
    from_name     VARCHAR(255) NOT NULL DEFAULT 'Materialverleih',
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_smtp_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO smtp_settings (id, host, port, encryption, username, password, from_address, from_name)
VALUES (1, '', 587, 'tls', NULL, NULL, '', 'Materialverleih');

-- ---------------------------------------------------------------------
-- Standard-Administrator-Benutzer, damit sich nach dem Deployment überhaupt
-- jemand anmelden kann. WICHTIG: das Passwort MUSS nach der ersten Anmeldung
-- geändert werden — deshalb must_change_password = 1. Zugangsdaten:
--   E-Mail:    admin@materialverleih.local
--   Passwort:  ChangeMe!2026
-- Der Hash unten wurde mit PHP password_hash() (Bcrypt, Cost 12) erzeugt.
-- ---------------------------------------------------------------------
INSERT INTO users (id, email, password_hash, permissions, two_factor_method, must_change_password, is_active)
VALUES (
    '00000000-0000-4000-8000-000000000001',
    'admin@materialverleih.local',
    '$2y$12$9DnE/1ApuLo1TWso1Maa.OwZMg4UP.AjQQJa4ncGM3ka/fF7K4Y9q',
    'administration',
    'none',
    1,
    1
);
