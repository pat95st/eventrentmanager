-- Migration für bereits bestehende Installationen: fügt die Case-Verwaltung
-- hinzu (Tabellen "case_items", "case_inventory_numbers", "case_content_items").
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/003_add_cases.sql

USE materialverleih;

CREATE TABLE case_items (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    name                VARCHAR(255) NOT NULL,
    manufacturer_id     CHAR(36)     NOT NULL,
    prefix              VARCHAR(20)  NULL,
    description         TEXT         NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_case_items_manufacturer
        FOREIGN KEY (manufacturer_id) REFERENCES manufacturers (id) ON DELETE RESTRICT,
    INDEX idx_case_items_manufacturer (manufacturer_id),
    UNIQUE KEY uq_case_items_manufacturer_prefix (manufacturer_id, prefix),
    FULLTEXT INDEX ftx_case_items_search (name, description)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE case_inventory_numbers (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    case_id             CHAR(36)     NOT NULL,
    number              VARCHAR(100) NOT NULL,
    acquisition_date    DATE         NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_case_inventory_numbers_case
        FOREIGN KEY (case_id) REFERENCES case_items (id) ON DELETE CASCADE,
    UNIQUE KEY uq_case_inventory_numbers_number (number),
    INDEX idx_case_inventory_numbers_case (case_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE case_content_items (
    id                          CHAR(36)     NOT NULL PRIMARY KEY,
    case_inventory_number_id    CHAR(36)     NOT NULL,
    article_id                  CHAR(36)     NOT NULL,
    quantity                    INT UNSIGNED NOT NULL,
    created_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_case_content_items_case_inventory_number
        FOREIGN KEY (case_inventory_number_id) REFERENCES case_inventory_numbers (id) ON DELETE CASCADE,
    CONSTRAINT fk_case_content_items_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_case_content_items_case_article (case_inventory_number_id, article_id),
    INDEX idx_case_content_items_case_inventory_number (case_inventory_number_id),
    INDEX idx_case_content_items_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
