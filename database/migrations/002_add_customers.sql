-- Migration für bereits bestehende Installationen: fügt die neue
-- Kundenverwaltung hinzu (Tabelle "customers").
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/002_add_customers.sql

USE materialverleih;

CREATE TABLE customers (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    type            ENUM('privat', 'geschaeftlich') NOT NULL,
    company_name    VARCHAR(255) NULL,      -- nur bei type = 'geschaeftlich' befüllt
    first_name      VARCHAR(150) NOT NULL,
    last_name       VARCHAR(150) NOT NULL,
    street          VARCHAR(255) NOT NULL,  -- Straße/Hausnummer
    address_line2   VARCHAR(255) NULL,      -- Adresszusatz
    postal_code     VARCHAR(20)  NOT NULL,
    city            VARCHAR(150) NOT NULL,
    phone           VARCHAR(50)  NULL,
    email           VARCHAR(255) NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customers_last_name (last_name),
    INDEX idx_customers_company_name (company_name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
