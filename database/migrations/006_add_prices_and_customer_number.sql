-- Migration für bereits bestehende Installationen: ergänzt
-- - "suppliers.customer_number" (unsere Kundennummer beim Lieferanten; optional)
-- - "articles.purchase_price" (Einkaufspreis; optional)
-- - "inventory_numbers.purchase_price" (eigener Einkaufspreis je Exemplar,
--   falls abweichend vom Artikel-Einkaufspreis; optional)
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/006_add_prices_and_customer_number.sql

USE materialverleih;

ALTER TABLE suppliers
    ADD COLUMN customer_number VARCHAR(100) NULL AFTER email;

ALTER TABLE articles
    ADD COLUMN purchase_price DECIMAL(10, 2) NULL AFTER dimensions;

ALTER TABLE inventory_numbers
    ADD COLUMN purchase_price DECIMAL(10, 2) NULL AFTER supplier_id;
