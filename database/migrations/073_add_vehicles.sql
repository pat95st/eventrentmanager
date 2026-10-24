-- Migration für bereits bestehende Installationen: fügt die
-- Fahrzeugverwaltung hinzu (Tabelle "vehicles", Kategorien "Auto" und
-- "Anhänger") sowie die Beladungsliste einer Veranstaltung
-- (Zuordnung einzelner ausgegebener Inventarnummern zu einem Fahrzeug,
-- Tabelle "rental_vehicle_loads") und die Zugfahrzeug-Zuordnung eines
-- Anhängers für die Anhängelast-Prüfung (Tabelle "rental_vehicle_tows").
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
--
-- Ausführen z. B. mit:
--   mysql -u root -p materialverleih < database/migrations/073_add_vehicles.sql

USE materialverleih;

-- ---------------------------------------------------------------------
-- Fahrzeug-Stammdaten. Je Fahrzeug wird die Zuladung (payload_kg) sowie
-- die Abmessung der Ladefläche als Freitext gespeichert. Anhängekupplung/
-- Anhängelast sind nur bei category = 'auto' relevant, das Leergewicht nur
-- bei category = 'anhaenger' (die Anwendung erzwingt das, nicht die
-- Datenbank, analog zu is19Inch/heightUnits bei Article).
-- ---------------------------------------------------------------------
CREATE TABLE vehicles (
    id                      CHAR(36)       NOT NULL PRIMARY KEY,
    category                ENUM('auto', 'anhaenger') NOT NULL,
    name                    VARCHAR(255)   NOT NULL,
    license_plate           VARCHAR(20)    NULL,
    payload_kg              DECIMAL(8, 2)  NULL,
    load_area_dimensions    VARCHAR(100)   NULL,
    tow_hitch_available     TINYINT(1)     NULL,
    tow_capacity_kg         DECIMAL(8, 2)  NULL,
    empty_weight_kg         DECIMAL(8, 2)  NULL,
    notes                   TEXT           NULL,
    created_at              DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vehicles_category (category),
    INDEX idx_vehicles_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Beladungsliste: ordnet eine konkret ausgegebene Inventarnummer
-- (rental_issued_items, siehe 005_add_rentals.sql) einem Fahrzeug zu.
-- UNIQUE auf rental_issued_item_id, da ein Posten immer nur in genau einem
-- Fahrzeug verladen sein kann.
-- ---------------------------------------------------------------------
CREATE TABLE rental_vehicle_loads (
    id                      CHAR(36) NOT NULL PRIMARY KEY,
    rental_id               CHAR(36) NOT NULL,
    rental_issued_item_id   CHAR(36) NOT NULL,
    vehicle_id              CHAR(36) NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_vehicle_loads_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_vehicle_loads_issued_item
        FOREIGN KEY (rental_issued_item_id) REFERENCES rental_issued_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_vehicle_loads_vehicle
        FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_vehicle_loads_issued_item (rental_issued_item_id),
    INDEX idx_rental_vehicle_loads_rental (rental_id),
    INDEX idx_rental_vehicle_loads_vehicle (vehicle_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Zugfahrzeug-Zuordnung je Veranstaltung: welches Auto zieht welchen
-- Anhänger, für die Prüfung "Leergewicht Anhänger + Beladung Anhänger <=
-- Anhängelast Auto" (siehe VehicleLoadCalculator). UNIQUE auf
-- (rental_id, towed_vehicle_id), da ein Anhänger je Veranstaltung nur von
-- einem Zugfahrzeug gezogen werden kann.
-- ---------------------------------------------------------------------
CREATE TABLE rental_vehicle_tows (
    id                  CHAR(36) NOT NULL PRIMARY KEY,
    rental_id           CHAR(36) NOT NULL,
    towed_vehicle_id    CHAR(36) NOT NULL,
    towing_vehicle_id   CHAR(36) NOT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_vehicle_tows_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT fk_rental_vehicle_tows_towed
        FOREIGN KEY (towed_vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_rental_vehicle_tows_towing
        FOREIGN KEY (towing_vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    UNIQUE KEY uq_rental_vehicle_tows_rental_towed (rental_id, towed_vehicle_id),
    INDEX idx_rental_vehicle_tows_rental (rental_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
