-- Migration für bereits bestehende Installationen: fügt die neuen
-- Präfix-Spalten für standardisierte Inventurnummern hinzu
-- ((Hersteller-Präfix)-(Artikel-Präfix)-(laufende Nummer), z. B. "BOS-AKS-0001").
--
-- WICHTIG:
--   - Bei bestehenden Herstellern/Artikeln bleibt "prefix" zunächst NULL.
--     Die Anwendung fordert das Präfix erst wieder ein, wenn ein Hersteller
--     bzw. Artikel das nächste Mal über die Weboberfläche gespeichert wird.
--   - Solange ein Präfix fehlt, kann für den betroffenen Artikel keine neue
--     Inventurnummer vergeben werden (bestehende Inventurnummern bleiben
--     unverändert und funktionieren weiterhin normal, auch in einem
--     abweichenden Format).
--   - Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/001_add_prefixes.sql

USE materialverleih;

ALTER TABLE manufacturers
    ADD COLUMN prefix VARCHAR(20) NULL AFTER name,
    ADD UNIQUE KEY uq_manufacturers_prefix (prefix);

ALTER TABLE articles
    ADD COLUMN prefix VARCHAR(20) NULL AFTER manufacturer_id,
    ADD UNIQUE KEY uq_articles_manufacturer_prefix (manufacturer_id, prefix);
