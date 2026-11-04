-- Migration 129 (Nutzerwunsch):
--  1. Stromverteiler: Schmelzsicherungen (Neozed/Diazed) als weiteres
--     Schutzorgan, drei einzelne 1-polige LS/Sicherungen je Phase, Phase an
--     einphasigen Schutzorganen und an einphasigen Abgängen hinter
--     dreiphasiger Absicherung.
--  2. Stromanschlüsse von Veranstaltungsorten: Absicherung per LS-Schalter
--     (bisher) oder Neozed/Diazed.
--     Hintergründe (Oberfläche/Anmeldung): Unschärfe einstellbar.
--  3. Leitungen: feste Regeln für bestehende Leitungs-Artikel nachziehen
--     (Hersteller "Generisch", immer Zubehör, nicht einzeln vermietbar, kein
--     Einkaufs-/Tagespreis, keine Firmware, kein Rackeinbau, keine
--     Abmessungen, Elektroprüfung nach Leitungstyp, keine Schutzklasse).

-- 1. Stromverteiler
ALTER TABLE power_distributor_devices
    ADD COLUMN fuse_system  VARCHAR(10) NULL AFTER characteristic,              -- neozed | diazed (nur device_type = fuse)
    ADD COLUMN three_single TINYINT(1)  NOT NULL DEFAULT 0 AFTER fuse_system,   -- 3 einzelne 1-polige Geräte (je Phase)
    ADD COLUMN phase        VARCHAR(2)  NULL AFTER three_single;                -- L1 | L2 | L3 bei einphasigen Schutzorganen

ALTER TABLE power_distributor_outlets
    ADD COLUMN phase VARCHAR(2) NULL AFTER device_id;                           -- L1 | L2 | L3 bei dreiphasiger Absicherung

-- 2. Veranstaltungsorte
ALTER TABLE venue_power_connections
    ADD COLUMN fuse_type VARCHAR(10) NOT NULL DEFAULT 'mcb' AFTER breaker_rating; -- mcb | neozed | diazed

-- 2b. Hintergründe: Unschärfe in Pixeln (0–20)
ALTER TABLE company_settings
    ADD COLUMN background_blur       TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER background_zoom,
    ADD COLUMN login_background_blur TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER login_background_zoom;

-- 3. Leitungen (Kategorie mit is_cable = 1)
UPDATE articles a
JOIN article_cables ac ON ac.article_id = a.id
JOIN cable_types ct ON ct.id = ac.cable_type_id
SET a.requires_electrical_inspection = ct.requires_electrical_inspection,
    a.protection_class = NULL
WHERE a.is_bundle = 0;

UPDATE articles a
JOIN categories c ON c.id = a.category_id AND c.is_cable = 1
SET a.dimensions = NULL,
    a.purchase_price = NULL,
    a.is_19_inch = 0, a.rack_width = '19', a.height_units = NULL, a.install_depth_cm = NULL,
    a.has_firmware = 0,
    a.is_billable = 0, a.daily_rental_price = NULL,
    a.is_rentable_individually = 0,
    a.protection_class = NULL
WHERE a.is_bundle = 0;

-- Hersteller "Generisch": IGNORE überspringt Leitungen, deren Präfix es bei
-- "Generisch" schon gibt (Präfix je Hersteller eindeutig) – diese beim
-- nächsten Bearbeiten mit neuem Präfix speichern.
UPDATE IGNORE articles a
JOIN categories c ON c.id = a.category_id AND c.is_cable = 1
JOIN (SELECT id FROM manufacturers WHERE is_generic = 1 ORDER BY name LIMIT 1) g
SET a.manufacturer_id = g.id
WHERE a.is_bundle = 0 AND (a.manufacturer_id IS NULL OR a.manufacturer_id <> g.id);

UPDATE cable_types SET protection_class = NULL;
