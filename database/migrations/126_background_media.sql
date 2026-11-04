-- Migration 126: Hintergründe überarbeitet
--  * Darstellung des Oberflächen-Hintergrunds: Deckkraft, Einpassung
--    (cover = Fläche füllen, contain = ganz anzeigen), Ausschnitt (X/Y in %)
--    und Zoom (100–300 %). background_image_filename darf seitdem auch ein
--    kurzes Video (MP4/WebM) sein.
--  * Eigener Hintergrund für die Anmeldeseite (auch Zwei-Faktor-Abfrage)
--    mit denselben Darstellungsoptionen.
ALTER TABLE company_settings
    ADD COLUMN background_opacity        TINYINT UNSIGNED  NOT NULL DEFAULT 100     AFTER background_image_filename,
    ADD COLUMN background_fit            VARCHAR(10)       NOT NULL DEFAULT 'cover' AFTER background_opacity,
    ADD COLUMN background_position_x     TINYINT UNSIGNED  NOT NULL DEFAULT 50      AFTER background_fit,
    ADD COLUMN background_position_y     TINYINT UNSIGNED  NOT NULL DEFAULT 50      AFTER background_position_x,
    ADD COLUMN background_zoom           SMALLINT UNSIGNED NOT NULL DEFAULT 100     AFTER background_position_y,
    ADD COLUMN login_background_filename VARCHAR(255)      NULL                     AFTER background_zoom,
    ADD COLUMN login_background_opacity    TINYINT UNSIGNED  NOT NULL DEFAULT 100     AFTER login_background_filename,
    ADD COLUMN login_background_fit        VARCHAR(10)       NOT NULL DEFAULT 'cover' AFTER login_background_opacity,
    ADD COLUMN login_background_position_x TINYINT UNSIGNED  NOT NULL DEFAULT 50      AFTER login_background_fit,
    ADD COLUMN login_background_position_y TINYINT UNSIGNED  NOT NULL DEFAULT 50      AFTER login_background_position_x,
    ADD COLUMN login_background_zoom       SMALLINT UNSIGNED NOT NULL DEFAULT 100     AFTER login_background_position_y;
