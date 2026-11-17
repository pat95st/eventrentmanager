-- Migration 173: average power of a device in addition to its peak power.
--
-- articles.input_power_w (migration 159) stays the peak power (maximum
-- consumption per type plate). articles.avg_power_w is the average
-- consumption in watts (NULL = unknown). When an average is recorded, the
-- application states and plans with it instead of the peak power.

ALTER TABLE articles
    ADD COLUMN avg_power_w INT UNSIGNED NULL AFTER input_power_w;
