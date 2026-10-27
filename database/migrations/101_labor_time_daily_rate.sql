-- Ermöglicht zusätzlich zum Stundensatz einen Tagessatz je Arbeitszeit-
-- Artikel (siehe App\Domain\LaborTime\LaborTimeArticle::$dailyRate).
-- daily_rate bleibt NULL-fähig: bestehende Arbeitszeit-Artikel haben
-- zunächst keinen Tagessatz definiert und können weiterhin ausschließlich
-- nach Stunden abgerechnet werden.
--
-- billing_unit an den jeweiligen Positionstabellen hält fest, mit welcher
-- Einheit eine konkrete Position abgerechnet wurde (siehe
-- App\Domain\LaborTime\LaborTimeBillingUnit) — NOT NULL mit Default 'hour',
-- damit bestehende Positionen unverändert als Stundenabrechnung gelten.

ALTER TABLE labor_time_articles
    ADD COLUMN daily_rate DECIMAL(10, 2) NULL AFTER hourly_rate;

ALTER TABLE offer_labor_items
    ADD COLUMN billing_unit ENUM('hour', 'day') NOT NULL DEFAULT 'hour' AFTER unit_rate_per_hour;

ALTER TABLE invoice_labor_items
    ADD COLUMN billing_unit ENUM('hour', 'day') NOT NULL DEFAULT 'hour' AFTER unit_rate_per_hour;
