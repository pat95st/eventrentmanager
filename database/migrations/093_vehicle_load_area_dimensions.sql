-- Ersetzt die bisherige Freitext-Spalte `load_area_dimensions`
-- ("300x180x200 cm") durch drei eigene Maß-Spalten (Länge/Breite/Höhe der
-- Ladefläche, jeweils in cm) plus ein Flag für eine offene Ladefläche
-- (Pritsche/Anhänger ohne Aufbau, d. h. keine Höhenbegrenzung) — Kundenwunsch,
-- damit die Maße z. B. für eine spätere Beladungsprüfung einzeln auswertbar
-- sind statt nur als Anzeige-Freitext.
--
-- DECIMAL(6,1) analog zu Article.installDepthCm (siehe database/schema.sql),
-- in der Domain-Klasse (Vehicle) aber als String geführt, um
-- Rundungsfehler zu vermeiden (siehe App\Domain\Vehicle\Vehicle-Docblock).
-- load_area_height_cm ist NULL, wenn load_area_open_top = 1 (die Anwendung
-- erzwingt das, nicht die Datenbank — siehe ValidatesVehicleInput, analog zu
-- Article::heightUnits bei is19Inch = 0).
ALTER TABLE vehicles
    DROP COLUMN load_area_dimensions,
    ADD COLUMN load_area_length_cm  DECIMAL(6, 1)  NULL AFTER payload_kg,
    ADD COLUMN load_area_width_cm   DECIMAL(6, 1)  NULL AFTER load_area_length_cm,
    ADD COLUMN load_area_height_cm  DECIMAL(6, 1)  NULL AFTER load_area_width_cm,
    ADD COLUMN load_area_open_top   TINYINT(1)     NOT NULL DEFAULT 0 AFTER load_area_height_cm;
