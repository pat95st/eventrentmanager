-- Ergänzt Angebote und Auftragsbestätigungen um eine optionale Verknüpfung
-- mit einer bestehenden Veranstaltung (Rental) — analog zu
-- invoices.source_rental_id (siehe 033_add_invoices.sql/
-- 094_invoice_flatrate_logistics_rental_link_templates.sql). Rein
-- informativ/organisatorisch, NICHT zu verwechseln mit
-- offers.converted_rental_id (wird erst NACH dem Umwandeln eines Angebots
-- in eine Veranstaltung gesetzt, siehe ConvertOfferToRentalHandler) —
-- source_rental_id hier ist umgekehrt: eine bereits VORHER bestehende
-- Veranstaltung, mit der das Dokument verknüpft wird (siehe
-- App\Domain\Offer\Offer::$sourceRentalId,
-- App\Domain\OrderConfirmation\OrderConfirmation::$sourceRentalId).
--
-- Nutzerwunsch: der Veranstaltungsort soll bei verknüpfter Veranstaltung
-- aus dieser abgeleitet werden statt separat editierbar zu sein (siehe
-- entsprechendes <script> in offers/form.php bzw.
-- order-confirmations/form.php) — bislang nur bei Rechnungen möglich
-- (invoices.source_rental_id existierte bereits), jetzt auch hier.

ALTER TABLE offers
    ADD COLUMN source_rental_id CHAR(36) NULL AFTER converted_rental_id,
    ADD CONSTRAINT fk_offers_source_rental
        FOREIGN KEY (source_rental_id) REFERENCES rentals (id) ON DELETE SET NULL;

ALTER TABLE order_confirmations
    ADD COLUMN source_rental_id CHAR(36) NULL AFTER source_offer_id,
    ADD CONSTRAINT fk_order_confirmations_source_rental
        FOREIGN KEY (source_rental_id) REFERENCES rentals (id) ON DELETE SET NULL;
