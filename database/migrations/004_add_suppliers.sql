-- Migration für bereits bestehende Installationen: fügt die
-- Lieferantenverwaltung hinzu (Tabelle "suppliers") und verknüpft
-- Inventurnummern optional mit einem Lieferanten.
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/004_add_suppliers.sql

USE materialverleih;

CREATE TABLE suppliers (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    type            ENUM('kauf', 'zumietung') NOT NULL,
    street          VARCHAR(255) NOT NULL,
    postal_code     VARCHAR(20)  NOT NULL,
    city            VARCHAR(150) NOT NULL,
    phone           VARCHAR(50)  NULL,
    email           VARCHAR(255) NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suppliers_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE inventory_numbers
    ADD COLUMN supplier_id CHAR(36) NULL AFTER acquisition_date,
    ADD CONSTRAINT fk_inventory_numbers_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
    ADD INDEX idx_inventory_numbers_supplier (supplier_id);
