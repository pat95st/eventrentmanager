-- Recurring events (rental series). A series is created from an existing
-- rental (its first occurrence); every occurrence stays an ordinary rental
-- that points to the series via rentals.series_id.
--
--  - recurrence_type: weekly | monthly_day | monthly_weekday | yearly | manual
--  - interval_n: every n weeks/months/years (ignored for manual)
--  - weekdays: comma separated ISO weekdays 1 (Mon) .. 7 (Sun) for weekly
--  - month_day: day of month for monthly_day (clamped to the month's length)
--  - week_of_month / weekday: n-th weekday for monthly_weekday (-1 = last)
--  - manual_dates: comma separated Y-m-d dates for manual
--  - ends_on / occurrence_count: end condition (exactly one is set);
--    occurrence_count counts from anchor_date (inclusive)
--  - anchor_date: first occurrence the rule is calculated from
--  - ended_at: series was ended, no further occurrences can be generated
--  - rentals.series_index: running number within the series ({nr})
--  - rentals.series_customized: dates were changed by hand, a later rule
--    change leaves this occurrence untouched
CREATE TABLE rental_series (
    id               CHAR(36)          NOT NULL PRIMARY KEY,
    name_template    VARCHAR(255)      NULL,
    recurrence_type  VARCHAR(20)       NOT NULL,
    interval_n       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    weekdays         VARCHAR(20)       NULL,
    month_day        TINYINT UNSIGNED  NULL,
    week_of_month    TINYINT           NULL,
    weekday          TINYINT UNSIGNED  NULL,
    manual_dates     TEXT              NULL,
    ends_on          DATE              NULL,
    occurrence_count SMALLINT UNSIGNED NULL,
    anchor_date      DATE              NOT NULL,
    source_rental_id CHAR(36)          NULL,
    ended_at         DATETIME          NULL,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_series_source_rental
        FOREIGN KEY (source_rental_id) REFERENCES rentals (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE rentals
    ADD COLUMN series_id         CHAR(36)          NULL AFTER deployment_days,
    ADD COLUMN series_index      SMALLINT UNSIGNED NULL AFTER series_id,
    ADD COLUMN series_customized TINYINT(1)        NOT NULL DEFAULT 0 AFTER series_index,
    ADD CONSTRAINT fk_rentals_series
        FOREIGN KEY (series_id) REFERENCES rental_series (id) ON DELETE SET NULL,
    ADD INDEX idx_rentals_series (series_id, start_date);
