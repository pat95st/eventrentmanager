-- Stellt die Angebots-/Rechnungs-PDF-Vorlagen (offer_pdf_settings,
-- invoice_pdf_settings) von einfachem Markdown auf ein vom neuen
-- WYSIWYG-Rich-Text-Editor geliefertes HTML-Fragment um (siehe
-- App\Infrastructure\RichText\HtmlSanitizer/HtmlToRichTextParser sowie
-- App\Infrastructure\Pdf\RichTextPdfRenderer) — ermöglicht echte Formatierung
-- (fett/kursiv/unterstrichen/Überschriften/Listen/Ausrichtung) im PDF selbst,
-- statt wie bisher nur reinen, von Markdown-Zeichen befreiten Fließtext.
--
-- Bestehende Markdown-Inhalte werden dabei NICHT in formatiertes HTML
-- übersetzt (kein "**fett**" wird zu "<strong>fett</strong>") — das wäre in
-- reinem SQL nur unzuverlässig möglich. Stattdessen werden sie
-- HTML-escaped und Zeilenumbrüche in <br> übersetzt, in einen einzelnen
-- <p>-Absatz gepackt: der bisherige Text bleibt vollständig und sichtbar
-- erhalten (inkl. etwaiger "**"/"#"-Zeichen als sichtbarer Text), geht beim
-- nächsten Öffnen im neuen Editor aber nicht verloren und kann dort bei
-- Bedarf manuell neu formatiert werden.
ALTER TABLE offer_pdf_settings
    ADD COLUMN intro_html   MEDIUMTEXT NULL AFTER intro_markdown,
    ADD COLUMN closing_html MEDIUMTEXT NULL AFTER closing_markdown;

UPDATE offer_pdf_settings
SET
    intro_html = CASE
        WHEN intro_markdown IS NULL OR TRIM(intro_markdown) = '' THEN NULL
        ELSE CONCAT('<p>', REPLACE(REPLACE(REPLACE(REPLACE(intro_markdown, '&', '&amp;'), '<', '&lt;'), '>', '&gt;'), '\n', '<br>'), '</p>')
    END,
    closing_html = CASE
        WHEN closing_markdown IS NULL OR TRIM(closing_markdown) = '' THEN NULL
        ELSE CONCAT('<p>', REPLACE(REPLACE(REPLACE(REPLACE(closing_markdown, '&', '&amp;'), '<', '&lt;'), '>', '&gt;'), '\n', '<br>'), '</p>')
    END
WHERE id = 1;

ALTER TABLE offer_pdf_settings
    DROP COLUMN intro_markdown,
    DROP COLUMN closing_markdown;

-- ---------------------------------------------------------------------

ALTER TABLE invoice_pdf_settings
    ADD COLUMN intro_html   MEDIUMTEXT NULL AFTER intro_markdown,
    ADD COLUMN closing_html MEDIUMTEXT NULL AFTER closing_markdown;

UPDATE invoice_pdf_settings
SET
    intro_html = CASE
        WHEN intro_markdown IS NULL OR TRIM(intro_markdown) = '' THEN NULL
        ELSE CONCAT('<p>', REPLACE(REPLACE(REPLACE(REPLACE(intro_markdown, '&', '&amp;'), '<', '&lt;'), '>', '&gt;'), '\n', '<br>'), '</p>')
    END,
    closing_html = CASE
        WHEN closing_markdown IS NULL OR TRIM(closing_markdown) = '' THEN NULL
        ELSE CONCAT('<p>', REPLACE(REPLACE(REPLACE(REPLACE(closing_markdown, '&', '&amp;'), '<', '&lt;'), '>', '&gt;'), '\n', '<br>'), '</p>')
    END
WHERE id = 1;

ALTER TABLE invoice_pdf_settings
    DROP COLUMN intro_markdown,
    DROP COLUMN closing_markdown;
