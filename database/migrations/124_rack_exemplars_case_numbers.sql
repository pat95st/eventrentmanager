-- Migration 124 (Nutzerwunsch):
-- 1. Racks werden zu Rack-Typen mit einzelnen Exemplaren (wie Cases). Jedes
--    Exemplar hat eine Rack-Nummer (RACK-0001 …, Nummernkreis aus Migration
--    120), eine Inventurnummer wie bei Artikeln (Hersteller-Kürzel-Rack-
--    Kürzel-0001), eine eigene Bestückung und optional ein fest zugeordnetes
--    Gerät. Bestehende Racks werden zu je einem Typ mit einem Exemplar.
-- 2. Case-Exemplare bekommen zusätzlich zur Inventurnummer (wieder Hersteller-
--    Kürzel-Case-Kürzel-0001) eine Case-Nummer (CASE-0001 …).

-- --- Racks ---------------------------------------------------------------
ALTER TABLE racks ADD COLUMN prefix VARCHAR(10) NULL AFTER name;

-- Hersteller ist jetzt Pflicht (Grundlage der Inventurnummer); ohne
-- Hersteller angelegte Racks bekommen "Generisch" (Migration 119).
UPDATE racks SET manufacturer_id = (SELECT id FROM manufacturers WHERE is_generic = 1 ORDER BY name LIMIT 1)
WHERE manufacturer_id IS NULL;

CREATE TABLE rack_exemplars (
    id                          CHAR(36)      NOT NULL PRIMARY KEY,
    rack_id                     CHAR(36)      NOT NULL,
    rack_number                 VARCHAR(40)   NULL,
    inventory_number            VARCHAR(100)  NOT NULL,
    fixed_inventory_number_id   CHAR(36)      NULL,
    acquisition_date            DATE          NULL,
    created_at                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rack_exemplars_rack
        FOREIGN KEY (rack_id) REFERENCES racks (id) ON DELETE CASCADE,
    CONSTRAINT fk_rack_exemplars_fixed_inventory_number
        FOREIGN KEY (fixed_inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE SET NULL,
    UNIQUE KEY uq_rack_exemplars_rack_number (rack_number),
    UNIQUE KEY uq_rack_exemplars_inventory_number (inventory_number),
    UNIQUE KEY uq_rack_exemplars_fixed_inventory_number (fixed_inventory_number_id),
    INDEX idx_rack_exemplars_rack (rack_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Jedes bisherige Rack wird zu einem Exemplar; die bisherige Nummer bleibt
-- Inventurnummer, eine RACK-Nummer wird übernommen bzw. neu vergeben.
INSERT INTO rack_exemplars (id, rack_id, rack_number, inventory_number, fixed_inventory_number_id, created_at, updated_at)
SELECT UUID(), id, CASE WHEN inventory_number REGEXP '^RACK-[0-9]+$' THEN inventory_number ELSE NULL END,
       inventory_number, fixed_inventory_number_id, created_at, updated_at
FROM racks;

SET @rack_n := GREATEST(
    (SELECT COALESCE(MAX(CAST(SUBSTRING(rack_number, 6) AS UNSIGNED)), 0) FROM rack_exemplars WHERE rack_number IS NOT NULL),
    (SELECT COALESCE(MAX(next_number), 1) - 1 FROM document_number_sequences WHERE document_type = 'rack')
);
UPDATE rack_exemplars SET rack_number = CONCAT('RACK-', LPAD(@rack_n := @rack_n + 1, 4, '0'))
WHERE rack_number IS NULL ORDER BY created_at, id;
INSERT INTO document_number_sequences (document_type, sequence_year, next_number) VALUES ('rack', 0, @rack_n + 1)
ON DUPLICATE KEY UPDATE next_number = GREATEST(next_number, @rack_n + 1);

-- Bestückung hängt jetzt am Exemplar.
ALTER TABLE rack_slots ADD COLUMN rack_exemplar_id CHAR(36) NULL AFTER id;
UPDATE rack_slots s JOIN rack_exemplars e ON e.rack_id = s.rack_id SET s.rack_exemplar_id = e.id;
ALTER TABLE rack_slots DROP FOREIGN KEY fk_rack_slots_rack;
ALTER TABLE rack_slots DROP INDEX idx_rack_slots_rack;
ALTER TABLE rack_slots DROP COLUMN rack_id;
ALTER TABLE rack_slots MODIFY rack_exemplar_id CHAR(36) NOT NULL;
ALTER TABLE rack_slots
    ADD CONSTRAINT fk_rack_slots_exemplar FOREIGN KEY (rack_exemplar_id) REFERENCES rack_exemplars (id) ON DELETE CASCADE,
    ADD INDEX idx_rack_slots_exemplar (rack_exemplar_id, start_unit);

-- Nummer und feste Zuordnung liegen jetzt am Exemplar.
ALTER TABLE racks DROP FOREIGN KEY fk_racks_fixed_inventory_number;
ALTER TABLE racks DROP INDEX uq_racks_fixed_inventory_number;
ALTER TABLE racks DROP INDEX uq_racks_inventory_number;
ALTER TABLE racks DROP COLUMN fixed_inventory_number_id, DROP COLUMN inventory_number;

-- --- Cases ---------------------------------------------------------------
ALTER TABLE case_inventory_numbers
    ADD COLUMN case_number VARCHAR(40) NULL AFTER number,
    ADD UNIQUE KEY uq_case_inventory_numbers_case_number (case_number);

-- Seit Migration 120 vergebene CASE-Nummern werden zur Case-Nummer, alle
-- übrigen Exemplare bekommen fortlaufend eine neue.
UPDATE case_inventory_numbers SET case_number = number WHERE number REGEXP '^CASE-[0-9]+$';
SET @case_n := GREATEST(
    (SELECT COALESCE(MAX(CAST(SUBSTRING(case_number, 6) AS UNSIGNED)), 0) FROM case_inventory_numbers WHERE case_number IS NOT NULL),
    (SELECT COALESCE(MAX(next_number), 1) - 1 FROM document_number_sequences WHERE document_type = 'case_item')
);
UPDATE case_inventory_numbers SET case_number = CONCAT('CASE-', LPAD(@case_n := @case_n + 1, 4, '0'))
WHERE case_number IS NULL ORDER BY created_at, id;
INSERT INTO document_number_sequences (document_type, sequence_year, next_number) VALUES ('case_item', 0, @case_n + 1)
ON DUPLICATE KEY UPDATE next_number = GREATEST(next_number, @case_n + 1);

-- Bezeichnungen in Einstellungen › Nummerierung
INSERT IGNORE INTO document_numbering_settings (document_type, scheme, prefix, `separator`, digit_count) VALUES
    ('case_item', 'simple', 'CASE-', '-', 4), ('rack', 'simple', 'RACK-', '-', 4);
