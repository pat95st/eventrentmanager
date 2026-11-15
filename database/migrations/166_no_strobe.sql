-- Migration 166: "No strobe" flag.
--
--  Events (rentals) and individual minute program items can be marked so
--  that no strobe lighting may be used (e.g. because of photosensitive
--  guests or venue rules). The flag on a template item is copied to and
--  from minute program items like the notes.

ALTER TABLE rentals
    ADD COLUMN no_strobe TINYINT(1) NOT NULL DEFAULT 0 AFTER blocks_other_rentals;

ALTER TABLE minute_program_items
    ADD COLUMN no_strobe TINYINT(1) NOT NULL DEFAULT 0 AFTER note_stage;

ALTER TABLE minute_program_template_items
    ADD COLUMN no_strobe TINYINT(1) NOT NULL DEFAULT 0 AFTER note_stage;
