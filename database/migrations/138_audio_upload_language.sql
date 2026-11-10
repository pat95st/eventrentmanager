-- Migration 138: Upload-Recht für Veranstalter-Zugänge und Sprache der Durchsagen
--  - rental_organizer_logins.can_upload: darf eigene Sounddateien hochladen
--    (Techniker/Admins dürfen das immer)
--  - rental_audio_settings.tts_language: feste Sprache für getippte Durchsagen
--    dieser Veranstaltung; NULL = Sprache wird in der Live-Oberfläche gewählt
--  - rental_playback_requests.language: Sprache je Durchsage (an das Abspielgerät)
ALTER TABLE rental_organizer_logins
    ADD COLUMN can_upload TINYINT(1) NOT NULL DEFAULT 0 AFTER can_schedule;
ALTER TABLE rental_audio_settings
    ADD COLUMN tts_language VARCHAR(10) NULL AFTER logo_filename;
ALTER TABLE rental_playback_requests
    ADD COLUMN language VARCHAR(10) NULL AFTER text;
