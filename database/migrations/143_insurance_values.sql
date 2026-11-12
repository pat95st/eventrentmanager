-- Migration 143: Zeitwerte für die Versicherungsliste einer Veranstaltung
--  - inventory_number_current_values: geschätzter Zeitwert je Inventarnummer
--    (händisch gepflegt oder per KI geschätzt, siehe source/note)
--  - article_fixed_current_values: fester Zeitwert je Artikel, gilt für alle
--    Exemplare ohne eigenen Zeitwert (z. B. XLR-Kabel)
--  - insurance_export_settings: zuletzt gewählte Spalten des
--    Versicherungslisten-Exports (CSV/PDF)

CREATE TABLE IF NOT EXISTS inventory_number_current_values (
    inventory_number_id CHAR(36)      NOT NULL PRIMARY KEY,
    current_value       DECIMAL(10,2) NOT NULL,
    source              VARCHAR(10)   NOT NULL DEFAULT 'manual',
    note                VARCHAR(500)  NULL,
    updated_at          DATETIME      NOT NULL,
    CONSTRAINT fk_inv_current_value_inventory_number FOREIGN KEY (inventory_number_id) REFERENCES inventory_numbers (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

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
