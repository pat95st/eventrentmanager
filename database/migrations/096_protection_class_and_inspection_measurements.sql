-- Schutzklasse am Artikel (nur relevant/Pflichtfeld, wenn
-- requires_electrical_inspection = 1, siehe Article::$protectionClass,
-- App\Domain\Article\ProtectionClass) sowie vier einzeln erfasste Messwerte
-- je Elektroprüfung (siehe App\Domain\ElectricalInspection\ElectricalInspection,
-- ersetzt die bisher rein freitextliche Erfassung in notes; notes bleibt für
-- sonstige Auffälligkeiten erhalten).

ALTER TABLE articles
    ADD COLUMN protection_class ENUM('I', 'II', 'III') NULL AFTER requires_electrical_inspection;

ALTER TABLE electrical_inspections
    ADD COLUMN protective_conductor_resistance_ohm DECIMAL(5, 3) NULL AFTER passed,  -- nur bei Schutzklasse I sinnvoll
    ADD COLUMN insulation_resistance_mohm DECIMAL(6, 2) NULL AFTER protective_conductor_resistance_ohm,
    ADD COLUMN protective_conductor_current_ma DECIMAL(6, 2) NULL AFTER insulation_resistance_mohm,       -- nur bei Schutzklasse I sinnvoll
    ADD COLUMN touch_current_ma DECIMAL(6, 2) NULL AFTER protective_conductor_current_ma;
