-- Migration für bereits bestehende Installationen: fügt die
-- Vermietungsverwaltung hinzu (Tabellen "rentals", "rental_items",
-- "rental_issued_items") für die Vermietung von Material an Endkunden
-- inklusive Ausgabe-Checkliste und Kalenderansicht.
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/005_add_rentals.sql

USE materialverleih;

CREATE TABLE rentals (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    customer_id       CHAR(36)     NOT NULL,
    event_name        VARCHAR(255) NOT NULL,
    event_location    VARCHAR(255) NOT NULL,
    start_date        DATE         NOT NULL,
    end_date          DATE         NOT NULL,
    deployment_days   INT UNSIGNED NOT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rentals_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
    INDEX idx_rentals_customer (customer_id),
    INDEX idx_rentals_dates (start_date, end_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_items (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id    CHAR(36)     NOT NULL,
    article_id   CHAR(36)     NOT NULL,
    quantity     INT UNSIGNED NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_items_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_items_rental_article (rental_id, article_id),
    INDEX idx_rental_items_rental (rental_id),
    INDEX idx_rental_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rental_issued_items (
    id                    CHAR(36)  NOT NULL PRIMARY KEY,
    rental_id             CHAR(36)  NOT NULL,
    article_id            CHAR(36)  NOT NULL,
    inventory_number_id   CHAR(36)  NOT NULL,
    issued_at             DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_issued_items_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_issued_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_rental_issued_items_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE RESTRICT,
    INDEX idx_rental_issued_items_rental (rental_id),
    INDEX idx_rental_issued_items_article (article_id),
    INDEX idx_rental_issued_items_inventory_number (inventory_number_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
