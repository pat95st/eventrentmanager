-- Migration für bereits bestehende Installationen: optionale Verknüpfung
-- einer Inventurnummer (inventory_numbers) mit einem Beleg (receipts, siehe
-- Migration 035).
--
-- Hintergrund: wird z. B. eine Sammel-Lieferantenrechnung für mehrere Stück
-- eines Artikels als Beleg erfasst, sollen alle dabei angelegten
-- Inventurnummern gemeinsam auf diesen einen Beleg verweisen können, statt
-- den Lieferanten (der über den Beleg ja bereits feststeht) bei jeder
-- Inventurnummer erneut auswählen zu müssen.
--
-- Der Lieferant wird dabei bewusst NICHT redundant auf inventory_numbers
-- kopiert: ist receipt_id gesetzt, bleibt supplier_id auf dieser Zeile null
-- und der Lieferant wird stattdessen zur Anzeigezeit über
-- receipt_id -> receipts.supplier_id aufgelöst (siehe
-- AddInventoryNumberHandler/UpdateInventoryNumberHandler sowie die Anzeige
-- in resources/views/articles/show.php). So gibt es weiterhin nur EINE
-- Quelle der Wahrheit für den Lieferanten und keine Gefahr, dass zwei
-- gespeicherte Werte auseinanderlaufen, wenn der Beleg nachträglich einem
-- anderen Lieferanten zugeordnet wird.
--
-- ON DELETE SET NULL ist hier bewusst gewählt, obwohl DeleteReceiptHandler
-- das Löschen eines Belegs, dem noch Inventurnummern zugeordnet sind,
-- bereits auf Anwendungsebene verhindert (ConflictException) — das ist der
-- eigentliche Schutzmechanismus. Der Fremdschlüssel dient hier nur als
-- Verteidigung in der Tiefe (defense in depth), z. B. falls ein Beleg auf
-- einem anderen Weg als über die Anwendung gelöscht wird.
USE materialverleih;

ALTER TABLE inventory_numbers
    ADD COLUMN receipt_id CHAR(36) NULL AFTER supplier_id,
    ADD CONSTRAINT fk_inventory_numbers_receipt FOREIGN KEY (receipt_id) REFERENCES receipts (id) ON DELETE SET NULL,
    ADD INDEX idx_inventory_numbers_receipt (receipt_id);
