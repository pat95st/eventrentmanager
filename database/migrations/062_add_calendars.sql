-- CalDAV-Server: Kalender (siehe App\Domain\Calendar\Calendar/CalendarKind),
-- Zugriffsrechte pro User sowie CalDAV-App-Passwörter (siehe
-- App\Domain\Calendar\CalDavCredential).

CREATE TABLE calendars (
    id                CHAR(36)                 NOT NULL PRIMARY KEY,
    kind              ENUM('event', 'rental')  NOT NULL,
    name              VARCHAR(255)              NOT NULL,
    enabled           TINYINT(1)                NOT NULL DEFAULT 0,
    -- Nur bei kind = 'rental' befüllt (eine oder mehrere Spalten, UND-
    -- verknüpft); bei kind = 'event' bleiben alle vier immer NULL.
    category_id       CHAR(36)                  NULL,
    manufacturer_id   CHAR(36)                  NULL,
    warehouse_id      CHAR(36)                  NULL,
    venue_id          CHAR(36)                  NULL,
    created_at        DATETIME                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME                  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_calendars_category
        FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_calendars_manufacturer
        FOREIGN KEY (manufacturer_id) REFERENCES manufacturers (id) ON DELETE SET NULL,
    CONSTRAINT fk_calendars_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    CONSTRAINT fk_calendars_venue
        FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL,
    INDEX idx_calendars_kind (kind)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Der eine, fest eingebaute Veranstaltungskalender — feste ID, damit er nur
-- einmal existiert (siehe DeleteCalendarHandler: kind = 'event' ist nicht
-- löschbar). Standardmäßig deaktiviert, bis ein Administrator ihn bewusst
-- einschaltet (siehe api_settings-Vorbild).
INSERT INTO calendars (id, kind, name, enabled) VALUES
    ('00000000-0000-4000-8000-000000000001', 'event', 'Veranstaltungen', 0);

CREATE TABLE calendar_access (
    id           CHAR(36) NOT NULL PRIMARY KEY,
    calendar_id  CHAR(36) NOT NULL,
    user_id      CHAR(36) NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_calendar_access_calendar
        FOREIGN KEY (calendar_id) REFERENCES calendars (id) ON DELETE CASCADE,
    CONSTRAINT fk_calendar_access_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY uq_calendar_access_calendar_user (calendar_id, user_id),
    INDEX idx_calendar_access_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE caldav_credentials (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    user_id           CHAR(36)     NOT NULL,
    name              VARCHAR(255) NOT NULL,
    token_hash        CHAR(64)     NOT NULL,
    token_last_four   CHAR(4)      NOT NULL,
    last_used_at      DATETIME     NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_caldav_credentials_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT uniq_caldav_credentials_token_hash UNIQUE (token_hash),
    INDEX idx_caldav_credentials_user (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
