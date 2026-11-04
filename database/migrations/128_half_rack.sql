-- Migration 128: 9,5-Zoll-Rackeinbaugeräte (Nutzerwunsch).
--  * articles.rack_width: Einbaubreite eines Rackeinbaugeräts (is_19_inch = 1):
--    '19' = volle 19-Zoll-Breite, '9.5' = halbe Breite (9,5 Zoll).
--  * rack_slots.side: bei einem 9,5-Zoll-Gerät in einem 19-Zoll-Rack die
--    belegte Hälfte ('left' | 'right'); NULL = ganze Rackbreite (19-Zoll-Gerät
--    in 19-Zoll-Rack, 9,5-Zoll-Gerät in 9,5-Zoll-Rack). So passen zwei
--    9,5-Zoll-Geräte nebeneinander in ein 19-Zoll-Rack.
-- 9,5-Zoll-Racks gibt es bereits seit Migration 115 (racks.width).
ALTER TABLE articles
    ADD COLUMN rack_width VARCHAR(5) NOT NULL DEFAULT '19' AFTER is_19_inch;

ALTER TABLE rack_slots
    ADD COLUMN side VARCHAR(5) NULL AFTER start_unit;
