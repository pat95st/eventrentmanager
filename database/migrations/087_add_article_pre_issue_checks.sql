-- Migration für bereits bestehende Installationen: Vor-Ausgabe-Checks für
-- Artikel (z. B. "Default Show bei Digitalpult geladen") — eine je Artikel
-- frei definierbare Liste von Prüfpunkten, die VOR der Ausgabe einer
-- Inventarnummer einmalig je Vermietungsposten bestätigt werden müssen.
-- Exakt analog zur Zubehör-Vollständigkeitsprüfung (siehe Migration 007 /
-- ArticleAccessory, rental_items.accessory_checked) — bewusst ein eigenes,
-- paralleles Feld, da ein Artikel sowohl Zubehör als auch Vor-Ausgabe-Checks
-- (oder beides, oder keins von beiden) haben kann.
--
-- article_pre_issue_checks: nur eine Freitext-Bezeichnung je Prüfpunkt
-- (anders als article_accessories keine Anzahl, da es sich um eine
-- durchzuführende Prüfung handelt, nicht um ein mitzugebendes Teil).
--
-- rental_items.pre_issue_checks_confirmed: wie accessory_checked ein reiner
-- Bestätigungs-Schalter je Posten (siehe ConfirmRentalItemPreIssueChecksHandler),
-- NICHT je ausgegebener Inventarnummer — die Checks gelten für den Artikel
-- als Ganzes, nicht für ein einzelnes Exemplar.
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/087_add_article_pre_issue_checks.sql

USE materialverleih;

CREATE TABLE article_pre_issue_checks (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    article_id   CHAR(36)     NOT NULL,
    description  VARCHAR(255) NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_pre_issue_checks_article
        FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
    INDEX idx_article_pre_issue_checks_article (article_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE rental_items
    ADD COLUMN pre_issue_checks_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER accessory_checked;
