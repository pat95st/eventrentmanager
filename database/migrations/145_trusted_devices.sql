-- Migration 145: "Dem Gerät für die nächsten 10 Tage vertrauen"
--  - trusted_devices: Geräte (Browser), auf denen ein Benutzer nach
--    erfolgreicher Zwei-Faktor-Anmeldung den Haken "Dem Gerät vertrauen"
--    gesetzt hat. Bis expires_at entfällt dort die Abfrage des zweiten
--    Faktors (Passwort bleibt immer Pflicht).
--  - Im Browser liegt nur ein Cookie "selector:validator". Gespeichert wird
--    hier der selector im Klartext (zum Nachschlagen) und vom validator nur
--    der SHA-256-Hash — ein Datenbank-Leck allein reicht also nicht, um
--    einem Gerät "vertrauen" zu lassen.
--  - Alle Einträge eines Benutzers werden gelöscht, sobald er sein Passwort
--    oder seinen zweiten Faktor ändert (bzw. ein Administrator das tut),
--    sowie manuell über Profil › Sicherheit.

CREATE TABLE trusted_devices (
    id            CHAR(36)     NOT NULL PRIMARY KEY,
    user_id       VARCHAR(36)  NOT NULL,
    selector      CHAR(24)     NOT NULL,
    token_hash    CHAR(64)     NOT NULL,
    user_agent    TEXT         NULL,
    ip_address    VARCHAR(45)  NULL,
    created_at    DATETIME     NOT NULL,
    expires_at    DATETIME     NOT NULL,
    UNIQUE KEY uq_trusted_devices_selector (selector),
    KEY idx_trusted_devices_user (user_id, expires_at),
    CONSTRAINT fk_trusted_devices_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
