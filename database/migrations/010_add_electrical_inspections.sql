-- Migration für bereits bestehende Installationen: legt die Tabelle für
-- Elektroprüfungen (z. B. DGUV V3) je Inventurnummer an.
USE materialverleih;

CREATE TABLE electrical_inspections (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    inventory_number_id     CHAR(36)     NOT NULL,
    inspected_at            DATE         NOT NULL,
    validity_years          TINYINT      NOT NULL DEFAULT 1,
    passed                  TINYINT(1)   NOT NULL DEFAULT 1,
    inspector                VARCHAR(255) NULL,
    notes                   TEXT         NULL,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_electrical_inspections_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE CASCADE,
    INDEX idx_electrical_inspections_inventory_number (inventory_number_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
