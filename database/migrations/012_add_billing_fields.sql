-- Migration für bereits bestehende Installationen: ergänzt je Artikel ein
-- Flag, ob er bei einer Vermietung als eigener Posten abgerechnet wird
-- (sonst gilt er als Zubehör, z. B. Kabel), sowie einen Preis pro Einsatztag
-- für abrechenbare Artikel.
USE materialverleih;

ALTER TABLE articles
    ADD COLUMN is_billable TINYINT(1) NOT NULL DEFAULT 1 AFTER requires_electrical_inspection,
    ADD COLUMN daily_rental_price DECIMAL(10, 2) NULL AFTER is_billable;
