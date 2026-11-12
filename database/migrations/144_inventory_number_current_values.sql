-- Migration 144: Zeitwerte für die Versicherungsliste einer Veranstaltung
--  - inventory_numbers.current_value*: geschätzter Zeitwert direkt an der
--    Inventarnummer (händisch im Inventarnummern-Formular gepflegt oder per KI
--    geschätzt, siehe current_value_source/current_value_note)
--  - article_fixed_current_values: fester Zeitwert je Artikel, gilt für alle
--    Exemplare ohne eigenen Zeitwert (z. B. XLR-Kabel)
--  - insurance_export_settings: zuletzt gewählte Spalten des
--    Versicherungslisten-Exports (CSV/PDF)
-- Idempotent geschrieben; übernimmt außerdem Werte aus der Vorab-Version
-- dieser Funktion (eigene Tabelle inventory_number_current_values).

ALTER TABLE inventory_numbers
    ADD COLUMN IF NOT EXISTS current_value            DECIMAL(10,2) NULL AFTER purchase_price,
    ADD COLUMN IF NOT EXISTS current_value_source     VARCHAR(10)   NULL AFTER current_value,
    ADD COLUMN IF NOT EXISTS current_value_note       VARCHAR(500)  NULL AFTER current_value_source,
    ADD COLUMN IF NOT EXISTS current_value_updated_at DATETIME      NULL AFTER current_value_note;

CREATE TABLE IF NOT EXISTS article_fixed_current_values (
    article_id    CHAR(36)      NOT NULL PRIMARY KEY,
    current_value DECIMAL(10,2) NOT NULL,
    updated_at    DATETIME      NOT NULL,
    CONSTRAINT fk_article_fixed_current_value_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS insurance_export_settings (
    id      TINYINT      NOT NULL PRIMARY KEY,
    fields  VARCHAR(255) NOT NULL,
    CONSTRAINT chk_insurance_export_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT IGNORE INTO insurance_export_settings (id, fields)
VALUES (1, 'category,manufacturer_model,inventory_number,acquisition_year,current_value,purchase_price');

-- Vorab-Version: Werte aus der früheren eigenen Tabelle übernehmen und diese entfernen.
CREATE TABLE IF NOT EXISTS inventory_number_current_values (
    inventory_number_id CHAR(36)      NOT NULL PRIMARY KEY,
    current_value       DECIMAL(10,2) NOT NULL,
    source              VARCHAR(10)   NOT NULL DEFAULT 'manual',
    note                VARCHAR(500)  NULL,
    updated_at          DATETIME      NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

UPDATE inventory_numbers i
    JOIN inventory_number_current_values v ON v.inventory_number_id = i.id
SET i.current_value = v.current_value,
    i.current_value_source = v.source,
    i.current_value_note = v.note,
    i.current_value_updated_at = v.updated_at
WHERE i.current_value IS NULL;

DROP TABLE inventory_number_current_values;
