-- Migration 130 (Nutzerwunsch):
--  1. Veranstaltungen können den Zeitraum für andere Vermietungen sperren
--     ("exklusiv", Checkbox im Veranstaltungsformular).
--  2. Betriebsurlaub (Einstellungen › Firma › Betriebsurlaub): Veranstaltungen,
--     die in einen Betriebsurlaub fallen, brauchen eine Abholung vor und eine
--     Rückgabe nach dem Urlaub.

ALTER TABLE rentals
    ADD COLUMN blocks_other_rentals TINYINT(1) NOT NULL DEFAULT 0 AFTER deployment_days;

CREATE TABLE company_holidays (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    start_date DATE         NOT NULL,
    end_date   DATE         NOT NULL,
    note       VARCHAR(255) NULL,
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    KEY idx_company_holidays_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
