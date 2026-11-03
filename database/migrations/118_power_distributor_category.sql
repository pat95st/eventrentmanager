-- Migration 118: Stromverteiler haben immer die Artikelkategorie
-- "Stromverteiler" (Nutzerwunsch). Die Kategorie wird über
-- categories.is_power_distributor erkannt (nicht über den Namen, damit sie
-- umbenannt werden kann). Eine vorhandene Hauptkategorie "Stromverteiler"
-- wird übernommen, sonst angelegt. Bereits erfasste Stromverteiler werden
-- dieser Kategorie zugeordnet.

ALTER TABLE categories
    ADD COLUMN is_power_distributor TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_id;

UPDATE categories SET is_power_distributor = 1
WHERE name = 'Stromverteiler'
ORDER BY (parent_id IS NOT NULL), created_at
LIMIT 1;

INSERT INTO categories (id, name, parent_id, is_power_distributor)
SELECT UUID(), 'Stromverteiler', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE is_power_distributor = 1);

UPDATE articles
SET category_id = (SELECT id FROM categories WHERE is_power_distributor = 1 LIMIT 1)
WHERE id IN (SELECT article_id FROM power_distributors);
