-- Migration für bereits bestehende Installationen:
--   venue_stage_risers: Riser (Podeste auf der Bühne, z. B. Drum-Riser), frei
--   auf einer Bühne platziert. Alle Längen in Metern.
--     shape         rectangle | circle | semicircle | triangle
--                   (siehe App\Domain\Venue\RiserShape)
--     width_m       Breite; bei Kreis/Halbkreis der Durchmesser
--     depth_m       Tiefe; nur bei Rechteck und Dreieck, sonst NULL
--     center_x_m    Mittelpunkt: Abstand von der linken Bühnenkante
--     center_y_m    Mittelpunkt: Abstand von der Bühnenhinterkante
--     rotation_deg  Drehung im Uhrzeigersinn um den Mittelpunkt (0–359)
CREATE TABLE venue_stage_risers (
    id            CHAR(36)          NOT NULL PRIMARY KEY,
    stage_id      CHAR(36)          NOT NULL,
    name          VARCHAR(100)      NULL,
    shape         VARCHAR(12)       NOT NULL,
    width_m       DECIMAL(5,2)      NOT NULL,
    depth_m       DECIMAL(5,2)      NULL,
    height_m      DECIMAL(4,2)      NOT NULL,
    center_x_m    DECIMAL(5,2)      NOT NULL,
    center_y_m    DECIMAL(5,2)      NOT NULL,
    rotation_deg  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_venue_stage_risers_stage
        FOREIGN KEY (stage_id) REFERENCES venue_stages (id) ON DELETE CASCADE,
    INDEX idx_venue_stage_risers_stage (stage_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
