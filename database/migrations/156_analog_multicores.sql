-- Migration 156: Analoge Multicore-Kabel als Spezialartikel (Nutzerwunsch,
-- analog zu digitalen Stageboxen/Tonmischpulten). Ein analoges Multicore hat
-- eine Anzahl Sends und Returns, liegt auf einer Trommel oder nicht und hat
-- Harting-Steckverbinder oder nicht.
-- Analoge Multicores haben immer die Kategorie "Analoges Multicore"
-- (categories.is_analog_multicore, umbenennbar, nicht löschbar).

ALTER TABLE categories
    ADD COLUMN is_analog_multicore TINYINT(1) NOT NULL DEFAULT 0 AFTER is_mixing_console;

UPDATE categories SET is_analog_multicore = 1
WHERE name IN ('Analoges Multicore', 'Analoge Multicores', 'Multicore (analog)', 'Multicores (analog)')
  AND is_power_distributor = 0 AND is_cable = 0 AND is_digital_stagebox = 0 AND is_mixing_console = 0
ORDER BY (parent_id IS NOT NULL), created_at
LIMIT 1;

INSERT INTO categories (id, name, parent_id, is_analog_multicore)
SELECT UUID(), 'Analoges Multicore', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE is_analog_multicore = 1);

CREATE TABLE article_analog_multicores (
    article_id   CHAR(36)          NOT NULL PRIMARY KEY,
    send_count   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    return_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    on_drum      TINYINT(1)        NOT NULL DEFAULT 0, -- auf Kabeltrommel
    has_harting  TINYINT(1)        NOT NULL DEFAULT 0, -- mit Harting-Steckverbinder
    CONSTRAINT fk_article_analog_multicores_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
