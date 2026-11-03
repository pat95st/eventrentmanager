-- Migration 117: Stromverteiler als Sonderform von Artikeln (Nutzerwunsch).
-- Zuleitung, Schutzorgane (RCD/FI, LS, FI/LS-Kombination) und Abgänge mit
-- Zuordnung zum vorgeschalteten Schutzorgan. Ein LS kann hinter einem RCD
-- liegen (upstream_device_id), Abgänge ohne Schutzorgan gehen direkt von der
-- Zuleitung ab.

CREATE TABLE power_distributors (
    article_id      CHAR(36)    NOT NULL PRIMARY KEY,
    inlet_type      VARCHAR(30) NOT NULL,
    notes           TEXT        NULL,
    updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_power_distributors_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE power_distributor_devices (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    article_id              CHAR(36)     NOT NULL,
    label                   VARCHAR(50)  NOT NULL,
    device_type             VARCHAR(10)  NOT NULL, -- rcd | mcb | rcbo
    poles                   TINYINT UNSIGNED NULL,
    rcd_type                VARCHAR(5)   NULL,     -- AC | A | F | B | B+
    residual_current_ma     INT UNSIGNED NULL,     -- Auslösestrom (Bemessungsdifferenzstrom)
    rated_current_a         INT UNSIGNED NULL,     -- Sicherungsgröße (LS) bzw. Nennstrom (RCD)
    characteristic          VARCHAR(2)   NULL,     -- B | C | D | K | Z
    upstream_device_id      CHAR(36)     NULL,     -- RCD vor einem LS
    sort_order              INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_pd_devices_article
        FOREIGN KEY (article_id) REFERENCES power_distributors (article_id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_devices_upstream
        FOREIGN KEY (upstream_device_id) REFERENCES power_distributor_devices (id) ON DELETE SET NULL,
    INDEX idx_pd_devices_article (article_id, sort_order)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE power_distributor_outlets (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    article_id      CHAR(36)     NOT NULL,
    connector_type  VARCHAR(30)  NOT NULL,
    quantity        INT UNSIGNED NOT NULL DEFAULT 1,
    label           VARCHAR(50)  NULL,
    device_id       CHAR(36)     NULL, -- null = direkt von der Zuleitung
    sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_pd_outlets_article
        FOREIGN KEY (article_id) REFERENCES power_distributors (article_id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_outlets_device
        FOREIGN KEY (device_id) REFERENCES power_distributor_devices (id) ON DELETE SET NULL,
    INDEX idx_pd_outlets_article (article_id, sort_order)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
