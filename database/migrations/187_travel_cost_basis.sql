-- Driver's logbook, part 5: travel costs from planned or driven kilometres.
--
--  - document_travel_costs.basis: what the section was taken over from:
--    'tour' (route of a tour, migration 182), 'planned' (planned trips of an
--    event, migration 186) or 'actual' (billable trips of the driver's
--    logbook, migration 184; invoices only).
--  - document_travel_costs.source_hash: fingerprint of the logbook trips at
--    the time of the import (basis 'actual'), to point out later changes.
--  - document_travel_costs.estimate_note: note printed below planned travel
--    costs on offers and order confirmations, copied from
--    vehicle_settings.travel_cost_estimate_note when the travel costs are
--    taken over (NULL = no note; can be switched off per document).
--  - document_travel_costs.trips: trips may carry their own price per km and
--    vehicle ({date, from, to, km, rate, vehicle}); trips of the logbook are
--    billed with the cost per km of their own vehicle (+ trailer) plus markup.
ALTER TABLE document_travel_costs
    ADD COLUMN basis              VARCHAR(10) NOT NULL DEFAULT 'tour' AFTER mode,
    ADD COLUMN source_hash        CHAR(64)    NULL AFTER basis,
    ADD COLUMN estimate_note      VARCHAR(1000) NULL AFTER source_hash;

ALTER TABLE vehicle_settings
    ADD COLUMN travel_cost_estimate_note VARCHAR(1000) NULL AFTER event_travel_costs_default;

UPDATE vehicle_settings
   SET travel_cost_estimate_note = 'Die Fahrtkosten basieren auf den geplanten Kilometern. Abgerechnet werden die tatsächlich gefahrenen Kilometer; der Betrag kann daher abweichen.'
 WHERE travel_cost_estimate_note IS NULL;
