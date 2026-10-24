-- Migration für bereits bestehende Installationen: das Veranstaltungsort-Feld
-- (event_location) der Vermietungen ist jetzt optional, da der Veranstaltungsort
-- im Formular nur noch als ein einziges Feld (freier Text mit Vorschlägen aus
-- den gespeicherten Veranstaltungsorten) erfasst wird und leer bleiben darf.
USE materialverleih;

ALTER TABLE rentals
    MODIFY COLUMN event_location VARCHAR(255) NULL;
