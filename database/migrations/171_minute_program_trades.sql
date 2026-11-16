-- Migration 171: notes per trade (Gewerk) in minute programs.
--
--  - minute_programs.trade_ids / minute_program_templates.trade_ids:
--    comma-separated trade IDs (table trades, migration 134) whose notes the
--    program offers and shows. NULL = automatic: the trades needed at the
--    event (rental_trades) plus the trades of its technicians, plus every
--    trade an item already has a note for.
--  - minute_program_items.trade_notes / minute_program_template_items.trade_notes:
--    JSON object {"<trade id>": "<note>"} replacing the fixed columns
--    note_sound, note_light, note_video and note_stage. note_general stays.
--
-- Existing notes are moved to the trades "Ton", "Licht", "Video" and
-- "Bühne" (created if missing and actually used), then the old columns are
-- dropped.

ALTER TABLE minute_programs
    ADD COLUMN trade_ids TEXT NULL AFTER headset_mic_count;

ALTER TABLE minute_program_templates
    ADD COLUMN trade_ids TEXT NULL AFTER headset_mic_count;

ALTER TABLE minute_program_items
    ADD COLUMN trade_notes TEXT NULL AFTER note_general;

ALTER TABLE minute_program_template_items
    ADD COLUMN trade_notes TEXT NULL AFTER note_general;

-- Create the trades for the old fixed note columns (only when needed).
INSERT INTO trades (id, name, sort_order, created_at, updated_at)
SELECT UUID(), 'Ton', 10, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM trades WHERE name = 'Ton')
  AND (EXISTS (SELECT 1 FROM minute_program_items WHERE note_sound IS NOT NULL AND note_sound <> '')
       OR EXISTS (SELECT 1 FROM minute_program_template_items WHERE note_sound IS NOT NULL AND note_sound <> ''));

INSERT INTO trades (id, name, sort_order, created_at, updated_at)
SELECT UUID(), 'Licht', 20, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM trades WHERE name = 'Licht')
  AND (EXISTS (SELECT 1 FROM minute_program_items WHERE note_light IS NOT NULL AND note_light <> '')
       OR EXISTS (SELECT 1 FROM minute_program_template_items WHERE note_light IS NOT NULL AND note_light <> ''));

INSERT INTO trades (id, name, sort_order, created_at, updated_at)
SELECT UUID(), 'Video', 30, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM trades WHERE name = 'Video')
  AND (EXISTS (SELECT 1 FROM minute_program_items WHERE note_video IS NOT NULL AND note_video <> '')
       OR EXISTS (SELECT 1 FROM minute_program_template_items WHERE note_video IS NOT NULL AND note_video <> ''));

INSERT INTO trades (id, name, sort_order, created_at, updated_at)
SELECT UUID(), 'Bühne', 40, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM trades WHERE name = 'Bühne')
  AND (EXISTS (SELECT 1 FROM minute_program_items WHERE note_stage IS NOT NULL AND note_stage <> '')
       OR EXISTS (SELECT 1 FROM minute_program_template_items WHERE note_stage IS NOT NULL AND note_stage <> ''));

-- Move the notes into trade_notes.
UPDATE minute_program_items i JOIN trades t ON t.name = 'Ton'
SET i.trade_notes = JSON_SET(COALESCE(i.trade_notes, '{}'), CONCAT('$."', t.id, '"'), i.note_sound)
WHERE i.note_sound IS NOT NULL AND i.note_sound <> '';

UPDATE minute_program_items i JOIN trades t ON t.name = 'Licht'
SET i.trade_notes = JSON_SET(COALESCE(i.trade_notes, '{}'), CONCAT('$."', t.id, '"'), i.note_light)
WHERE i.note_light IS NOT NULL AND i.note_light <> '';

UPDATE minute_program_items i JOIN trades t ON t.name = 'Video'
SET i.trade_notes = JSON_SET(COALESCE(i.trade_notes, '{}'), CONCAT('$."', t.id, '"'), i.note_video)
WHERE i.note_video IS NOT NULL AND i.note_video <> '';

UPDATE minute_program_items i JOIN trades t ON t.name = 'Bühne'
SET i.trade_notes = JSON_SET(COALESCE(i.trade_notes, '{}'), CONCAT('$."', t.id, '"'), i.note_stage)
WHERE i.note_stage IS NOT NULL AND i.note_stage <> '';

UPDATE minute_program_template_items i JOIN trades t ON t.name = 'Ton'
SET i.trade_notes = JSON_SET(COALESCE(i.trade_notes, '{}'), CONCAT('$."', t.id, '"'), i.note_sound)
WHERE i.note_sound IS NOT NULL AND i.note_sound <> '';

UPDATE minute_program_template_items i JOIN trades t ON t.name = 'Licht'
SET i.trade_notes = JSON_SET(COALESCE(i.trade_notes, '{}'), CONCAT('$."', t.id, '"'), i.note_light)
WHERE i.note_light IS NOT NULL AND i.note_light <> '';

UPDATE minute_program_template_items i JOIN trades t ON t.name = 'Video'
SET i.trade_notes = JSON_SET(COALESCE(i.trade_notes, '{}'), CONCAT('$."', t.id, '"'), i.note_video)
WHERE i.note_video IS NOT NULL AND i.note_video <> '';

UPDATE minute_program_template_items i JOIN trades t ON t.name = 'Bühne'
SET i.trade_notes = JSON_SET(COALESCE(i.trade_notes, '{}'), CONCAT('$."', t.id, '"'), i.note_stage)
WHERE i.note_stage IS NOT NULL AND i.note_stage <> '';

ALTER TABLE minute_program_items
    DROP COLUMN note_sound,
    DROP COLUMN note_light,
    DROP COLUMN note_video,
    DROP COLUMN note_stage;

ALTER TABLE minute_program_template_items
    DROP COLUMN note_sound,
    DROP COLUMN note_light,
    DROP COLUMN note_video,
    DROP COLUMN note_stage;
