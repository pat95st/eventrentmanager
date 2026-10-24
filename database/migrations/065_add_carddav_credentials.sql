-- CardDAV-Server: App-Passwörter für den Kontaktzugriff (siehe
-- App\Domain\AddressBook\CardDavCredential). Die zwei Adressbücher selbst
-- ("Kunden"/"Lieferanten", siehe App\Domain\AddressBook\AddressBook) sind
-- fest im Code hinterlegt und brauchen — anders als die frei konfigurierbaren
-- Kalender (siehe 062_add_calendars.sql) — keine eigene Tabelle: sie zeigen
-- immer alle Kunden/Lieferanten, geschützt durch die bereits vorhandenen
-- Berechtigungen Kundenverwaltung/Artikelverwaltung.

CREATE TABLE carddav_credentials (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    user_id           CHAR(36)     NOT NULL,
    name              VARCHAR(255) NOT NULL,
    token_hash        CHAR(64)     NOT NULL,
    token_last_four   CHAR(4)      NOT NULL,
    last_used_at      DATETIME     NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_carddav_credentials_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uniq_carddav_credentials_token_hash UNIQUE (token_hash),
    INDEX idx_carddav_credentials_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
