-- Migration 165: Whether haze/fog machines may be used at a venue.
--
--  - venues.fog_policy: 'allowed', 'not_allowed' or 'on_request' (fog only
--    after consultation, the fire alarm system is temporarily disabled).
--    NULL = not specified (default for all existing venues).

ALTER TABLE venues
    ADD COLUMN fog_policy VARCHAR(20) NULL AFTER caretaker_phone;
