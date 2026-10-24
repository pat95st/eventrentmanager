-- Regelungen für das Überziehen der Mietzeit (siehe
-- App\Domain\Settings\OverageSettings): Singleton-Zeile analog zu
-- pricing_settings/invoice_settings.
--
-- Die Regel "überzogene Einsatztage werden zum vollen Tagespreis (Tag 1 der
-- Preisstaffel, siehe price_tiers) ohne Rabattierung berechnet" ist bewusst
-- KEIN eigenes Feld hier — sie ist feste Geschäftsregel (kein Umschalter
-- gewünscht) und wird auf der Einstellungsseite nur erläuternd angezeigt.
-- Konfigurierbar ist ausschließlich die zusätzliche Überzugspauschale.

CREATE TABLE overage_settings (
    id               TINYINT        NOT NULL PRIMARY KEY DEFAULT 1,
    flat_fee_enabled TINYINT(1)     NOT NULL DEFAULT 0,
    flat_fee_amount  DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    CONSTRAINT chk_overage_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO overage_settings (id, flat_fee_enabled, flat_fee_amount) VALUES (1, 0, 0.00);
