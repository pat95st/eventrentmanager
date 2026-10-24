-- 19-Zoll-Geräte: Kennzeichnung plus (nur relevant, wenn gesetzt) Anzahl der
-- Höheneinheiten (Rack-Einheiten, z. B. 3 für ein 3-HE-Gerät) und Einbautiefe
-- in cm (z. B. für die Planung, ob das Gerät in ein bestimmtes Rack passt).
ALTER TABLE articles
    ADD COLUMN is_19_inch        TINYINT(1)     NOT NULL DEFAULT 0 AFTER dimensions,
    ADD COLUMN height_units      SMALLINT UNSIGNED NULL AFTER is_19_inch,
    ADD COLUMN install_depth_cm  DECIMAL(6, 1)  NULL AFTER height_units;
