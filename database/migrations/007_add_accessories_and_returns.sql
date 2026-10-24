-- Migration für bereits bestehende Installationen: ergänzt
-- - "article_accessories" (Zubehör zu einem Artikel ohne eigene Inventurnummer,
--   nur Anzahl + Bezeichnung)
-- - "rental_items.accessory_checked" (Vollständigkeitsprüfung des Zubehörs bei
--   der Ausgabe; siehe ConfirmRentalItemAccessoriesHandler)
-- - "rental_issued_items.returned_at" und "rental_issued_items.function_test_passed"
--   (Rückgabe-Checkliste inkl. Funktionstest; bei fehlgeschlagenem Test wird
--   automatisch ein RMA-Vorgang angelegt, siehe ReturnRentalItemHandler)
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/007_add_accessories_and_returns.sql

USE materialverleih;

CREATE TABLE article_accessories (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    article_id   CHAR(36)     NOT NULL,
    quantity     INT UNSIGNED NOT NULL,
    description  VARCHAR(255) NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_accessories_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_article_accessories_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE rental_items
    ADD COLUMN accessory_checked TINYINT(1) NOT NULL DEFAULT 0 AFTER quantity;

ALTER TABLE rental_issued_items
    ADD COLUMN returned_at DATETIME NULL AFTER issued_at,
    ADD COLUMN function_test_passed TINYINT(1) NULL AFTER returned_at;
