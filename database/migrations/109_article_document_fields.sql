-- Nutzerwunsch: optionaler Kurztext und Dokumentbild je Artikel für
-- Angebote, Auftragsbestätigungen und Rechnungen.
--
-- * articles.document_short_text: erscheint im PDF klein und kursiv unter
--   dem Artikelnamen.
-- * articles.document_image_id: ein Bild aus der Galerie des Artikels
--   (article_images.id), das im PDF klein links neben Name/Kurztext steht.
--   Bewusst ohne Fremdschlüssel (articles <-> article_images würde beim
--   Löschen eines Artikels eine zirkuläre Kaskade erzeugen); beim Löschen
--   des Bildes setzt DeleteArticleImageHandler die Spalte zurück, ein
--   verwaister Wert wird beim Erzeugen der PDFs ignoriert.

ALTER TABLE articles
    ADD COLUMN document_short_text VARCHAR(500) NULL AFTER description,
    ADD COLUMN document_image_id CHAR(36) NULL AFTER document_short_text;
