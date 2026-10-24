-- Migration für bereits bestehende Installationen:
--   1. Vorname/Nachname am Benutzerkonto (nullable, da bestehende/seed
--      Benutzer sie noch nicht gesetzt haben; das Anlage-Formular verlangt
--      sie künftig als Pflichtfeld, siehe CreateUserHandler).
--   2. password_hash wird NULLABLE: ein Administrator kann einen Benutzer
--      OHNE Erstpasswort anlegen ("Per E-Mail einladen") — der Benutzer legt
--      sein Passwort dann selbst über einen zugesandten Link fest (siehe
--      password_setup_tokens unten sowie App\Application\Auth\
--      PasswordSetupTokenIssuer). Ein NULL-Hash bedeutet "kein Passwort
--      gesetzt" und lässt jede password_verify()-Anmeldung scheitern (siehe
--      AuthenticatePasswordHandler).
--   3. must_setup_two_factor erzwingt (wie must_change_password) das
--      Einrichten eines zweiten Faktors beim ersten Login, siehe
--      App\Infrastructure\Http\Auth\AuthGuard.
--   4. password_setup_tokens: kurzlebige, einmal verwendbare Tokens (analog
--      zu webauthn_challenges/login_challenges) für sowohl die
--      Erstpasswort-Einladung als auch "Passwort vergessen" — beide Wege
--      münden im selben "Passwort festlegen"-Formular (siehe
--      SetupPasswordController).
USE materialverleih;

ALTER TABLE users
    ADD COLUMN first_name VARCHAR(100) NULL AFTER email,
    ADD COLUMN last_name VARCHAR(100) NULL AFTER first_name,
    MODIFY COLUMN password_hash VARCHAR(255) NULL,
    ADD COLUMN must_setup_two_factor TINYINT(1) NOT NULL DEFAULT 0 AFTER must_change_password;

CREATE TABLE password_setup_tokens (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    user_id      CHAR(36)     NOT NULL,
    token_hash   VARCHAR(255) NOT NULL,
    expires_at   DATETIME     NOT NULL,
    used_at      DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_setup_tokens_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uq_password_setup_tokens_token_hash UNIQUE (token_hash),
    INDEX idx_password_setup_tokens_user (user_id),
    INDEX idx_password_setup_tokens_expires_at (expires_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
