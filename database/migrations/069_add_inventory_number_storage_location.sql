-- Freitext-Lagerort je Inventurnummer (z. B. "Regal 3, Fach B", "Lager Nord"),
-- damit sich pro physischem Exemplar notieren lässt, wo es aktuell steht.
-- Bewusst nur ein einfaches Textfeld auf der Inventurnummer selbst — es gibt
-- (noch) kein strukturiertes Lagerort-/Regal-Fach-Konzept im System; das
-- bestehende App\Domain\Warehouse\Warehouse ist ein gröberes Konzept
-- (Standort/Gebäude) und wird hierfür nicht verwendet.
ALTER TABLE inventory_numbers
    ADD COLUMN storage_location VARCHAR(255) NULL AFTER purchase_price;
