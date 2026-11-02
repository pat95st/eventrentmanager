-- Migration 115: Racks (Nutzerwunsch). Jedes Rack ist ein einzelnes
-- physisches Teil mit eigener Inventarnummer. Bestückt wird es mit konkreten
-- Geräte-Exemplaren (19-Zoll-Artikel) an einer HE-Position. Optional ist das
-- Rack einem Geräte-Exemplar fest zugeordnet und geht dann bei dessen
-- Ausgabe in einer Veranstaltung automatisch mit.

CREATE TABLE racks (
    id                          CHAR(36)      NOT NULL PRIMARY KEY,
    name                        VARCHAR(255)  NOT NULL,
    inventory_number            VARCHAR(100)  NOT NULL,
    manufacturer_id             CHAR(36)      NULL,
    height_units                INT UNSIGNED  NOT NULL,
    install_depth_cm            DECIMAL(6,1)  NULL,
    rail_type                   VARCHAR(20)   NOT NULL DEFAULT 'front', -- front | front_back
    width                       VARCHAR(10)   NOT NULL DEFAULT '19',    -- 19 | 9.5 (Zoll)
    color                       VARCHAR(100)  NULL,
    has_shock_mount             TINYINT(1)    NOT NULL DEFAULT 0,
    has_wheels                  TINYINT(1)    NOT NULL DEFAULT 0,
    fixed_inventory_number_id   CHAR(36)      NULL,
    description                 TEXT          NULL,
    created_at                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_racks_manufacturer
        FOREIGN KEY (manufacturer_id) REFERENCES manufacturers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_racks_fixed_inventory_number
        FOREIGN KEY (fixed_inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE SET NULL,
    UNIQUE KEY uq_racks_inventory_number (inventory_number),
    UNIQUE KEY uq_racks_fixed_inventory_number (fixed_inventory_number_id),
    INDEX idx_racks_manufacturer (manufacturer_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rack_slots (
    id                      CHAR(36)     NOT NULL PRIMARY KEY,
    rack_id                 CHAR(36)     NOT NULL,
    inventory_number_id     CHAR(36)     NOT NULL,
    start_unit              INT UNSIGNED NOT NULL, -- oberste belegte HE, von oben gezählt ab 1
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rack_slots_rack
        FOREIGN KEY (rack_id) REFERENCES racks (id) ON DELETE CASCADE,
    CONSTRAINT fk_rack_slots_inventory_number
        FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE CASCADE,
    UNIQUE KEY uq_rack_slots_inventory_number (inventory_number_id), -- ein Gerät steckt in höchstens einem Rack
    INDEX idx_rack_slots_rack (rack_id, start_unit)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
