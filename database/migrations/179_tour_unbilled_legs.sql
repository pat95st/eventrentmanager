-- Legs of a tour route that are not billed (e.g. a technician without
-- material rides along with someone else between two stops).
--
--  - rental_tour_stops.arrival_not_billed: 1 = the drive TO this stop,
--    waypoint or stored destination is left out of the billable kilometres
--    of the tour. The leg is still shown on the map and in the leg table and
--    still counts for driving times.
ALTER TABLE rental_tour_stops
    ADD COLUMN arrival_not_billed TINYINT(1) NOT NULL DEFAULT 0 AFTER depart_at;
