-- Migration 137: eigenes Logo je Veranstaltung für die Live-Oberfläche
-- (Audio-Dashboard). Wird nur Veranstalter-Zugängen angezeigt; Techniker
-- und Admins sehen immer das Firmenlogo.
ALTER TABLE rental_audio_settings
    ADD COLUMN logo_filename VARCHAR(255) NULL AFTER gong_by_default;
