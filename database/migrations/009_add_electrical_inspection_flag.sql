-- Migration für bereits bestehende Installationen: ergänzt ein einfaches
-- Flag "Elektroprüfung nötig" (DGUV V3 o. ä.) je Artikel.
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/009_add_electrical_inspection_flag.sql

USE materialverleih;

ALTER TABLE articles
    ADD COLUMN requires_electrical_inspection TINYINT(1) NOT NULL DEFAULT 0 AFTER purchase_price;
