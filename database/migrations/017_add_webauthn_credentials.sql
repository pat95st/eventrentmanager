-- Migration für bereits bestehende Installationen: Passkey-/WebAuthn-Anmeldung
-- als zusätzlicher, gleichwertiger Anmeldeweg neben Passwort (+ optionalem
-- zweitem Faktor). webauthn_credentials hält die pro Benutzer registrierten
-- Authenticatoren (Public Key im COSE-Format als JSON, siehe
-- App\Infrastructure\WebAuthn\WebAuthnService), webauthn_challenges die
-- kurzlebigen, genau einmal verwendbaren Challenges für Registrierung und
-- Anmeldung (siehe App\Domain\Auth\WebauthnChallenge).
USE materialverleih;

CREATE TABLE webauthn_credentials (
    id                VARCHAR(36)   NOT NULL PRIMARY KEY,
    user_id           VARCHAR(36)   NOT NULL,
    credential_id     VARCHAR(1024) NOT NULL,
    public_key_cose   TEXT          NOT NULL,
    sign_count        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    nickname          VARCHAR(100)  NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at      DATETIME      NULL,
    CONSTRAINT fk_webauthn_credentials_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uq_webauthn_credentials_credential_id UNIQUE (credential_id(255))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_webauthn_credentials_user_id ON webauthn_credentials (user_id);

CREATE TABLE webauthn_challenges (
    id           VARCHAR(36)  NOT NULL PRIMARY KEY,
    user_id      VARCHAR(36)  NULL,
    challenge    VARCHAR(255) NOT NULL,
    type         VARCHAR(20)  NOT NULL,
    expires_at   DATETIME     NOT NULL,
    consumed_at  DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_webauthn_challenges_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uq_webauthn_challenges_challenge UNIQUE (challenge),
    CONSTRAINT chk_webauthn_challenges_type CHECK (type IN ('registration', 'authentication'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE INDEX idx_webauthn_challenges_expires_at ON webauthn_challenges (expires_at);
