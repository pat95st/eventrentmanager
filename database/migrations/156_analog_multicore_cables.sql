-- Migration 156: Analoge Multicores in der Leitungsverwaltung (Nutzerwunsch).
-- Ein Leitungstyp kann als "analoges Multicore" markiert werden
-- (cable_types.is_analog_multicore). Leitungen dieses Typs haben neben
-- Länge und Steckverbindern (article_cables) die Anzahl Sends und Returns,
-- liegen auf einer Trommel oder nicht und haben Harting-Steckverbinder oder
-- nicht (article_cable_multicores). Analoge Multicores brauchen nie eine
-- Elektroprüfung.

ALTER TABLE cable_types
    ADD COLUMN is_analog_multicore TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_electrical_inspection;

CREATE TABLE article_cable_multicores (
    article_id    CHAR(36)          NOT NULL PRIMARY KEY,
    send_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    return_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    on_drum       TINYINT(1)        NOT NULL DEFAULT 0, -- auf Kabeltrommel
    has_harting   TINYINT(1)        NOT NULL DEFAULT 0, -- mit Harting-Steckverbinder
    CONSTRAINT fk_article_cable_multicores_cable
        FOREIGN KEY (article_id) REFERENCES article_cables (article_id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Beispiel-Grundausstattung (kann in Einstellungen › Leitungen geändert werden)
INSERT IGNORE INTO cable_connector_types (id, name, sort_order) VALUES
    ('c1560000-0000-0000-0000-000000000001', 'Harting-Stecker', 60),
    ('c1560000-0000-0000-0000-000000000002', 'Harting-Buchse', 61),
    ('c1560000-0000-0000-0000-000000000003', 'Multicore-Anschlusskasten', 62),
    ('c1560000-0000-0000-0000-000000000004', 'XLR-Fächerpeitsche', 63);

INSERT IGNORE INTO cable_types (id, name, description, requires_electrical_inspection, is_analog_multicore, sort_order) VALUES
    ('b1560000-0000-0000-0000-000000000001', 'Analoges Multicore', NULL, 0, 1, 100);

UPDATE cable_types SET is_analog_multicore = 1, requires_electrical_inspection = 0
WHERE name = 'Analoges Multicore';

INSERT IGNORE INTO cable_type_connectors (cable_type_id, connector_type_id)
SELECT t.id, c.id
FROM cable_types t
JOIN cable_connector_types c
  ON c.name IN ('Harting-Stecker', 'Harting-Buchse', 'Multicore-Anschlusskasten', 'XLR-Fächerpeitsche', 'XLR male', 'XLR female')
WHERE t.name = 'Analoges Multicore';
