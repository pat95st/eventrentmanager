-- Migration 125: Leitungen als Spezialartikel (Nutzerwunsch, analog
-- Stromverteiler). In Einstellungen › Leitungen werden Leitungstypen (mit
-- Vorgabe zur Elektroprüfung) und Steckverbindertypen gepflegt sowie, welche
-- Steckverbinder an welchem Leitungstyp sinnvoll sind. Ein Leitungs-Artikel
-- besteht aus Leitungstyp, Länge und zwei Steckverbindern.
-- Leitungen haben immer die Kategorie "Leitungen" (categories.is_cable).

ALTER TABLE categories
    ADD COLUMN is_cable TINYINT(1) NOT NULL DEFAULT 0 AFTER is_power_distributor;

UPDATE categories SET is_cable = 1
WHERE name IN ('Leitungen', 'Kabel') AND is_power_distributor = 0
ORDER BY (name <> 'Leitungen'), (parent_id IS NOT NULL), created_at
LIMIT 1;

INSERT INTO categories (id, name, parent_id, is_cable)
SELECT UUID(), 'Leitungen', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE is_cable = 1);

CREATE TABLE cable_connector_types (
    id          CHAR(36)      NOT NULL PRIMARY KEY,
    name        VARCHAR(150)  NOT NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cable_connector_types_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE cable_types (
    id                              CHAR(36)      NOT NULL PRIMARY KEY,
    name                            VARCHAR(150)  NOT NULL,
    description                     VARCHAR(500)  NULL,
    requires_electrical_inspection  TINYINT(1)    NOT NULL DEFAULT 0,
    protection_class                VARCHAR(5)    NULL, -- Vorgabe für die Schutzklasse (I/II/III)
    sort_order                      INT           NOT NULL DEFAULT 0,
    created_at                      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cable_types_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Welche Steckverbinder an welchem Leitungstyp sinnvoll sind
CREATE TABLE cable_type_connectors (
    cable_type_id      CHAR(36) NOT NULL,
    connector_type_id  CHAR(36) NOT NULL,
    PRIMARY KEY (cable_type_id, connector_type_id),
    CONSTRAINT fk_cable_type_connectors_type
        FOREIGN KEY (cable_type_id) REFERENCES cable_types (id) ON DELETE CASCADE,
    CONSTRAINT fk_cable_type_connectors_connector
        FOREIGN KEY (connector_type_id) REFERENCES cable_connector_types (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_cables (
    article_id      CHAR(36)      NOT NULL PRIMARY KEY,
    cable_type_id   CHAR(36)      NOT NULL,
    length_m        DECIMAL(7,2)  NOT NULL,
    connector_a_id  CHAR(36)      NOT NULL,
    connector_b_id  CHAR(36)      NOT NULL,
    CONSTRAINT fk_article_cables_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_cables_type
        FOREIGN KEY (cable_type_id) REFERENCES cable_types (id) ON DELETE RESTRICT,
    CONSTRAINT fk_article_cables_connector_a
        FOREIGN KEY (connector_a_id) REFERENCES cable_connector_types (id) ON DELETE RESTRICT,
    CONSTRAINT fk_article_cables_connector_b
        FOREIGN KEY (connector_b_id) REFERENCES cable_connector_types (id) ON DELETE RESTRICT,
    INDEX idx_article_cables_type (cable_type_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Beispiel-Grundausstattung (kann in den Einstellungen geändert werden)
INSERT INTO cable_connector_types (id, name, sort_order) VALUES
    ('c0000000-0000-0000-0000-000000000001', 'Schuko-Stecker', 10),
    ('c0000000-0000-0000-0000-000000000002', 'Schuko-Kupplung', 11),
    ('c0000000-0000-0000-0000-000000000003', 'CEE 16 A Stecker blau', 20),
    ('c0000000-0000-0000-0000-000000000004', 'CEE 16 A Kupplung blau', 21),
    ('c0000000-0000-0000-0000-000000000005', 'CEE 16 A Stecker rot', 30),
    ('c0000000-0000-0000-0000-000000000006', 'CEE 16 A Kupplung rot', 31),
    ('c0000000-0000-0000-0000-000000000007', 'CEE 32 A Stecker rot', 40),
    ('c0000000-0000-0000-0000-000000000008', 'CEE 32 A Kupplung rot', 41),
    ('c0000000-0000-0000-0000-000000000009', 'XLR male', 50),
    ('c0000000-0000-0000-0000-000000000010', 'XLR female', 51);

INSERT INTO cable_types (id, name, requires_electrical_inspection, protection_class, sort_order) VALUES
    ('b0000000-0000-0000-0000-000000000001', 'H07RN-F 3G2,5', 1, 'I', 10),
    ('b0000000-0000-0000-0000-000000000002', 'H07RN-F 5G2,5', 1, 'I', 20),
    ('b0000000-0000-0000-0000-000000000003', 'H07RN-F 5G6', 1, 'I', 30),
    ('b0000000-0000-0000-0000-000000000004', 'Mikrofonkabel 2×0,22 mm²', 0, NULL, 40);

INSERT INTO cable_type_connectors (cable_type_id, connector_type_id) VALUES
    ('b0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001'),
    ('b0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000002'),
    ('b0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000003'),
    ('b0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000004'),
    ('b0000000-0000-0000-0000-000000000002', 'c0000000-0000-0000-0000-000000000005'),
    ('b0000000-0000-0000-0000-000000000002', 'c0000000-0000-0000-0000-000000000006'),
    ('b0000000-0000-0000-0000-000000000003', 'c0000000-0000-0000-0000-000000000007'),
    ('b0000000-0000-0000-0000-000000000003', 'c0000000-0000-0000-0000-000000000008'),
    ('b0000000-0000-0000-0000-000000000004', 'c0000000-0000-0000-0000-000000000009'),
    ('b0000000-0000-0000-0000-000000000004', 'c0000000-0000-0000-0000-000000000010');
