-- Migration 134 (Nutzerwunsch): Recht "Techniker", Gewerke und
-- Techniker-Einsatzplanung je Veranstaltung.
--  - trades: Gewerke (Einstellungen › Lager & Veranstaltungen › Techniker & Gewerke)
--  - technician_profiles: je Techniker-Benutzer, ob er direkt verplant werden
--    kann oder erst zustimmen muss, sowie Messenger-Kontakt (Mobilnummer für
--    WhatsApp/Signal, Telegram-Chat-ID)
--  - rental_trades: an einer Veranstaltung benötigte Gewerke
--  - rental_technicians: Techniker einer Veranstaltung mit Status
--    (requested | confirmed | declined | assigned), bedienten Gewerken und
--    Erinnerungszähler
--  - technician_settings: wann unbeantwortete Anfragen erneut erinnert werden

CREATE TABLE trades (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    UNIQUE KEY uq_trades_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE technician_profiles (
    user_id               CHAR(36)     NOT NULL PRIMARY KEY,
    requires_confirmation TINYINT(1)   NOT NULL DEFAULT 1,
    phone                 VARCHAR(50)  NULL,
    telegram_chat_id      VARCHAR(100) NULL,
    updated_at            DATETIME     NOT NULL,
    CONSTRAINT fk_technician_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_trades (
    rental_id CHAR(36) NOT NULL,
    trade_id  CHAR(36) NOT NULL,
    PRIMARY KEY (rental_id, trade_id),
    CONSTRAINT fk_rental_trades_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_trades_trade FOREIGN KEY (trade_id) REFERENCES trades (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_technicians (
    id               CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id        CHAR(36)     NOT NULL,
    user_id          CHAR(36)     NOT NULL,
    status           VARCHAR(20)  NOT NULL,
    trade_ids        VARCHAR(1000) NULL,
    note             VARCHAR(500) NULL,
    requested_at     DATETIME     NOT NULL,
    responded_at     DATETIME     NULL,
    reminder_count   INT          NOT NULL DEFAULT 0,
    last_notified_at DATETIME     NULL,
    created_at       DATETIME     NOT NULL,
    updated_at       DATETIME     NOT NULL,
    UNIQUE KEY uq_rental_technicians (rental_id, user_id),
    KEY idx_rental_technicians_user (user_id, status),
    KEY idx_rental_technicians_status (status),
    CONSTRAINT fk_rental_technicians_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_technicians_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE technician_settings (
    id             TINYINT      NOT NULL PRIMARY KEY,
    reminder_hours VARCHAR(100) NOT NULL DEFAULT '24,72',
    updated_at     DATETIME     NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO technician_settings (id, reminder_hours, updated_at) VALUES (1, '24,72', NOW());
