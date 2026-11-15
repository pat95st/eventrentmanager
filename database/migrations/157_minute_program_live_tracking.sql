-- Migration 157: Live-Ablauf am FOH (Minutenprogramm-Regie) und Stagedisplay-API.
--
--  - current_delay_seconds: sekundengenauer Verzug (auch negativ). Ersetzt
--    fachlich current_delay_minutes; diese Spalte wird weiterhin (gerundet)
--    mitgeschrieben, damit ältere Auswertungen/Sicherungen lesbar bleiben.
--  - live_item_id / live_item_started_at: zuletzt per "Start" ausgelöster
--    Programmpunkt und sein tatsächlicher Beginn. Solange gesetzt, bleibt
--    dieser Punkt "aktuell", bis der nächste gestartet wird (überzieht er,
--    wächst der prognostizierte Verzug live mit).
--  - live_finished_at: Programm am FOH als beendet markiert.
ALTER TABLE minute_programs
    ADD COLUMN current_delay_seconds INT          NOT NULL DEFAULT 0 AFTER current_delay_minutes,
    ADD COLUMN live_item_id          CHAR(36)     NULL AFTER current_delay_seconds,
    ADD COLUMN live_item_started_at  DATETIME     NULL AFTER live_item_id,
    ADD COLUMN live_finished_at      DATETIME     NULL AFTER live_item_started_at;

UPDATE minute_programs SET current_delay_seconds = current_delay_minutes * 60;
