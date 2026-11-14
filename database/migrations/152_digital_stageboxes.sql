-- Migration 152: Digitale Stageboxen als Spezialartikel (Nutzerwunsch, analog
-- Stromverteiler/Leitungen). In Einstellungen › Digitale Protokolle werden
-- die digitalen Audioprotokolle (Dante, AES50, MADI, …) gepflegt. Eine
-- digitale Stagebox hat genau ein Protokoll, analoge Eingänge (beliebig
-- kombiniert aus XLR- und Kombibuchsen) und Ausgänge (beliebig kombiniert
-- aus analogen und AES/EBU-Ausgängen).
-- Digitale Stageboxen haben immer die Kategorie "Digitale Stagebox"
-- (categories.is_digital_stagebox, umbenennbar, nicht löschbar).

ALTER TABLE categories
    ADD COLUMN is_digital_stagebox TINYINT(1) NOT NULL DEFAULT 0 AFTER is_cable;

UPDATE categories SET is_digital_stagebox = 1
WHERE name IN ('Digitale Stagebox', 'Digitale Stageboxen') AND is_power_distributor = 0 AND is_cable = 0
ORDER BY (parent_id IS NOT NULL), created_at
LIMIT 1;

INSERT INTO categories (id, name, parent_id, is_digital_stagebox)
SELECT UUID(), 'Digitale Stagebox', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE is_digital_stagebox = 1);

CREATE TABLE digital_protocols (
    id          CHAR(36)      NOT NULL PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_digital_protocols_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE article_digital_stageboxes (
    article_id      CHAR(36)          NOT NULL PRIMARY KEY,
    protocol_id     CHAR(36)          NOT NULL,
    inputs_xlr      SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- analoge Eingänge, XLR-Buchse
    inputs_combo    SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- analoge Eingänge, Kombibuchse (XLR/Klinke)
    outputs_analog  SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- analoge Ausgänge
    outputs_aes     SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- AES/EBU-Ausgänge
    CONSTRAINT fk_article_digital_stageboxes_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_digital_stageboxes_protocol
        FOREIGN KEY (protocol_id) REFERENCES digital_protocols (id) ON DELETE RESTRICT,
    INDEX idx_article_digital_stageboxes_protocol (protocol_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Beispiel-Grundausstattung (kann in den Einstellungen geändert werden)
INSERT INTO digital_protocols (id, name, sort_order) VALUES
    ('d1000000-0000-0000-0000-000000000001', 'Dante', 10),
    ('d1000000-0000-0000-0000-000000000002', 'AES50', 20),
    ('d1000000-0000-0000-0000-000000000003', 'AVB / Milan', 30),
    ('d1000000-0000-0000-0000-000000000004', 'MADI', 40),
    ('d1000000-0000-0000-0000-000000000005', 'AES67', 50);
