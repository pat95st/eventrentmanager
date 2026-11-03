-- Migration 119: Hersteller "Generisch" (Kürzel GEN) für selbst gebaute
-- Artikel wie Kabel (Nutzerwunsch). Ein Artikel braucht weiterhin einen
-- Hersteller; "Generisch" wird bewusst von Hand ausgewählt. Erkennung über
-- manufacturers.is_generic: der Name erscheint dann nicht vor der
-- Artikelbezeichnung, und der Hersteller kann nicht gelöscht werden.

ALTER TABLE manufacturers
    ADD COLUMN is_generic TINYINT(1) NOT NULL DEFAULT 0 AFTER prefix;

-- Vorhandenen Hersteller "Generisch" übernehmen (Kürzel GEN setzen, falls frei) …
UPDATE manufacturers SET is_generic = 1 WHERE name = 'Generisch' LIMIT 1;
UPDATE manufacturers m
SET m.prefix = 'GEN'
WHERE m.is_generic = 1
  AND (m.prefix IS NULL OR m.prefix = '')
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM manufacturers WHERE prefix = 'GEN') AS taken);

-- … sonst anlegen (Kürzel GEN, falls kein anderer Hersteller es belegt).
INSERT INTO manufacturers (id, name, prefix, is_generic)
SELECT UUID(), 'Generisch',
       IF(EXISTS (SELECT 1 FROM manufacturers WHERE prefix = 'GEN'), NULL, 'GEN'),
       1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM manufacturers WHERE is_generic = 1);
