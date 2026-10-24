-- Stammdaten-Katalog für abrechenbare Arbeitszeit-Typen (z. B. "Techniker-
-- Stunde", "Meister-Stunde"), analog zu manufacturers — siehe
-- App\Domain\LaborTime\LaborTimeArticle. Der Stundensatz wird beim
-- Hinzufügen zu einem Angebot/einer Rechnung auf die jeweilige Position
-- gesnapshotet (siehe 050_add_offer_labor_items.sql /
-- 051_add_invoice_labor_items.sql), spätere Änderungen hier wirken sich
-- also NICHT auf bereits gespeicherte Positionen aus.
CREATE TABLE labor_time_articles (
    id           CHAR(36)      NOT NULL PRIMARY KEY,
    name         VARCHAR(255)  NOT NULL,
    hourly_rate  DECIMAL(10, 2) NOT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_labor_time_articles_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
