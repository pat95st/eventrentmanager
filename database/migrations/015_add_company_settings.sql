-- Migration für bereits bestehende Installationen: Firmeneinstellungen
-- (Name/Adresse/Logo), analog zur bereits bestehenden Singleton-Tabelle
-- smtp_settings. Wird auf der Detailseite/im Briefkopf verwendet.
USE materialverleih;

CREATE TABLE company_settings (
    id            TINYINT      NOT NULL PRIMARY KEY DEFAULT 1,
    name          VARCHAR(255) NULL,
    street        VARCHAR(255) NULL,
    postal_code   VARCHAR(20)  NULL,
    city          VARCHAR(255) NULL,
    country       VARCHAR(255) NULL,
    logo_filename VARCHAR(255) NULL,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_company_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO company_settings (id, name, street, postal_code, city, country, logo_filename)
VALUES (1, NULL, NULL, NULL, NULL, NULL, NULL);
