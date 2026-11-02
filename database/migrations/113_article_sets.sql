-- Nutzerwunsch: Sets (feste Zusammenstellung aus Artikeln/Bundles mit
-- Festpreis und fester Anzahl Einsatztage), zeitliche Regeln, Upgrades,
-- Einbindung in Angebot/AB/Rechnung und Veranstaltungen sowie Starttexte
-- je Dokumenttyp mit Anrede-Platzhalter.

CREATE TABLE article_sets (
    id              CHAR(36)      NOT NULL PRIMARY KEY,
    name            VARCHAR(255)  NOT NULL,
    description     TEXT          NULL,
    price           DECIMAL(10,2) NOT NULL,
    deployment_days INT UNSIGNED  NOT NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_set_items (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    set_id     CHAR(36)     NOT NULL,
    article_id CHAR(36)     NOT NULL, -- Artikel oder Bundle
    quantity   INT UNSIGNED NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    CONSTRAINT fk_article_set_items_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_set_items_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_article_set_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_set_rules (
    id             CHAR(36)    NOT NULL PRIMARY KEY,
    set_id         CHAR(36)    NOT NULL,
    type           ENUM('weekends', 'weekdays', 'yearly_period', 'date_range') NOT NULL,
    from_month_day CHAR(5)     NULL, -- "MM-DD", nur yearly_period
    to_month_day   CHAR(5)     NULL,
    from_date      DATE        NULL, -- nur date_range
    to_date        DATE        NULL,
    sort_order     INT         NOT NULL DEFAULT 0,
    CONSTRAINT fk_article_set_rules_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_set_upgrades (
    id         CHAR(36)      NOT NULL PRIMARY KEY,
    set_id     CHAR(36)      NOT NULL,
    name       VARCHAR(255)  NOT NULL,
    price      DECIMAL(10,2) NOT NULL,
    sort_order INT           NOT NULL DEFAULT 0,
    CONSTRAINT fk_article_set_upgrades_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_set_upgrade_items (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    upgrade_id CHAR(36)     NOT NULL,
    article_id CHAR(36)     NOT NULL,
    quantity   INT UNSIGNED NOT NULL,
    CONSTRAINT fk_article_set_upgrade_items_upgrade FOREIGN KEY (upgrade_id) REFERENCES article_set_upgrades (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_set_upgrade_items_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    INDEX idx_article_set_upgrade_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Set- und Upgrade-Positionen in Dokumenten: Freitext-Zeile mit Verweis.
ALTER TABLE offer_free_text_items
    ADD COLUMN set_id CHAR(36) NULL,
    ADD COLUMN set_upgrade_id CHAR(36) NULL,
    ADD CONSTRAINT fk_offer_free_text_items_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_offer_free_text_items_upgrade FOREIGN KEY (set_upgrade_id) REFERENCES article_set_upgrades (id) ON DELETE SET NULL;
ALTER TABLE order_confirmation_free_text_items
    ADD COLUMN set_id CHAR(36) NULL,
    ADD COLUMN set_upgrade_id CHAR(36) NULL,
    ADD CONSTRAINT fk_oc_free_text_items_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_oc_free_text_items_upgrade FOREIGN KEY (set_upgrade_id) REFERENCES article_set_upgrades (id) ON DELETE SET NULL;
ALTER TABLE invoice_free_text_items
    ADD COLUMN set_id CHAR(36) NULL,
    ADD COLUMN set_upgrade_id CHAR(36) NULL,
    ADD CONSTRAINT fk_invoice_free_text_items_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_invoice_free_text_items_upgrade FOREIGN KEY (set_upgrade_id) REFERENCES article_set_upgrades (id) ON DELETE SET NULL;

-- In Veranstaltungen gebuchte Sets und die dafür eingeplanten Artikel
-- (Bundle-Bestandteile bereits aufgelöst, inkl. Alternativen).
CREATE TABLE rental_set_bookings (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id   CHAR(36)     NOT NULL,
    set_id      CHAR(36)     NOT NULL,
    quantity    INT UNSIGNED NOT NULL,
    upgrade_ids TEXT         NULL, -- kommagetrennte article_set_upgrades.id
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_set_bookings_rental FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_set_bookings_set FOREIGN KEY (set_id) REFERENCES article_sets (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_set_allocations (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    booking_id CHAR(36)     NOT NULL,
    article_id CHAR(36)     NOT NULL,
    quantity   INT UNSIGNED NOT NULL,
    CONSTRAINT fk_rental_set_allocations_booking FOREIGN KEY (booking_id) REFERENCES rental_set_bookings (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_set_allocations_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_set_allocations (booking_id, article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Anrede bei Kunden und Ansprechpartnern ('herr', 'frau', 'divers'; NULL = keine).
ALTER TABLE customers ADD COLUMN salutation VARCHAR(10) NULL AFTER type;
ALTER TABLE customer_contacts ADD COLUMN salutation VARCHAR(10) NULL AFTER customer_id;

-- Starttext je Dokumenttyp ('offer', 'order_confirmation', 'invoice').
CREATE TABLE document_intro_texts (
    document_type VARCHAR(30) NOT NULL PRIMARY KEY,
    text          TEXT        NULL,
    updated_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
