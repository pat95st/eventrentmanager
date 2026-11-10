-- Migration 139: zyklische Einspieler (z. B. alle 10 Minuten, jede Stunde)
-- Ein geplanter Einspieler mit repeat_interval_minutes ist eine Serie: nach
-- jeder Wiedergabe rückt play_at um das Intervall vor, bis repeat_until bzw.
-- repeat_max erreicht ist (spätestens zum Ende des Veranstaltungszeitraums).
ALTER TABLE scheduled_playbacks
    ADD COLUMN repeat_interval_minutes INT NULL AFTER note,
    ADD COLUMN repeat_until DATETIME NULL AFTER repeat_interval_minutes,
    ADD COLUMN repeat_max INT NULL AFTER repeat_until,
    ADD COLUMN repeat_count INT NOT NULL DEFAULT 0 AFTER repeat_max;
