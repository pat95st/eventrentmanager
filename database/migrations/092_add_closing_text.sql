-- Freitext ("Schlusstext") auf Angebot/Auftragsbestätigung/Rechnung, der in
-- der Detailansicht und im PDF-Export jeweils NACH der Positionsliste und
-- der Summenzeile erscheint (Kundenwunsch) — anders als die bestehende
-- Spalte `note` ("Anmerkung"), die im Kopfbereich VOR der Positionsliste
-- gezeigt wird (siehe resources/views/invoices/show.php und die jeweiligen
-- *PdfGenerator-Klassen).
--
-- Bewusst pro Dokument statt global: anders als
-- invoice_pdf_settings.closing_html/offer_pdf_settings.closing_html (ein
-- fester Text für ALLE Dokumente dieses Typs) kann hier jedes einzelne
-- Angebot/jede Auftragsbestätigung/Rechnung einen eigenen Schlusstext
-- erhalten. Format identisch: bereits serverseitig bereinigtes Rich-Text-
-- HTML (siehe App\Infrastructure\RichText\HtmlSanitizer), gerendert über
-- denselben App\Infrastructure\Pdf\RichTextPdfRenderer wie der globale
-- Schlusstext.
ALTER TABLE offers
    ADD COLUMN closing_text MEDIUMTEXT NULL AFTER note;

ALTER TABLE order_confirmations
    ADD COLUMN closing_text MEDIUMTEXT NULL AFTER note;

ALTER TABLE invoices
    ADD COLUMN closing_text MEDIUMTEXT NULL AFTER note;
