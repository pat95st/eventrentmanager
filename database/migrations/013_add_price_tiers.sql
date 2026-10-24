-- Migration für bereits bestehende Installationen: legt die Preisstaffelung
-- für die Vermietungsdauer an (Aufschlag in % auf den Tagesmietsatz eines
-- Artikels, je nach Anzahl Einsatztage, 1 bis 7). Tag 1 entspricht per
-- Definition 100 % (= der hinterlegte Preis pro Einsatztag selbst) und wird
-- hier nur der Vollständigkeit halber mitgeführt, ist aber nicht über die
-- Oberfläche änderbar (siehe UpdatePriceTiersHandler).
USE materialverleih;

CREATE TABLE price_tiers (
    day_count   TINYINT        NOT NULL PRIMARY KEY,
    percentage  DECIMAL(6, 2)  NOT NULL,
    CONSTRAINT chk_price_tiers_day_count CHECK (day_count BETWEEN 1 AND 7)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO price_tiers (day_count, percentage) VALUES
    (1, 100.00),
    (2, 150.00),
    (3, 175.00),
    (4, 190.00),
    (5, 200.00),
    (6, 210.00),
    (7, 220.00);
