-- Ermöglicht Angebote, Auftragsbestätigungen und Rechnungen OHNE Zeitraum
-- (Start-/Enddatum) anzulegen, damit diese Dokumente auch unabhängig von
-- einer konkreten Veranstaltung erstellt werden können (siehe
-- App\Domain\Offer\Offer::$startDate/$endDate, App\Domain\Invoice\Invoice::
-- $startDate/$endDate, App\Domain\OrderConfirmation\OrderConfirmation::
-- $startDate/$endDate — alle drei jetzt ?DateTimeImmutable). Fehlt der
-- Zeitraum, wird für die Preisstaffelung (Tagessatz-Multiplikator) 1 Tag
-- angenommen, siehe Offer::deploymentDaysForPricing()/
-- Invoice::deploymentDaysForPricing().
--
-- Bestehende Datensätze haben immer einen Zeitraum (NOT NULL bisher) und
-- bleiben davon unberührt — die Spalten werden lediglich NULL-fähig, kein
-- Backfill nötig.

ALTER TABLE offers
    MODIFY COLUMN start_date DATE NULL,
    MODIFY COLUMN end_date DATE NULL;

ALTER TABLE invoices
    MODIFY COLUMN start_date DATE NULL,
    MODIFY COLUMN end_date DATE NULL;

ALTER TABLE order_confirmations
    MODIFY COLUMN start_date DATE NULL,
    MODIFY COLUMN end_date DATE NULL;
