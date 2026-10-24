-- Migration für bereits bestehende Installationen: fügt die neue
-- "Rechnung per E-Mail"-Vorlage (email_templates, Schlüssel invoice_pdf,
-- siehe App\Domain\Mail\EmailTemplateKey::InvoicePdf) hinzu, analog zur
-- offer_pdf-Zeile aus Migration 028.
--
-- Ergänzt außerdem die neue Mail-Kategorie "invoice_pdf" (siehe
-- App\Domain\Mail\MailCategory::InvoicePdf) an die bestehende
-- archive_categories-Liste der smtp_settings-Singleton-Zeile (Migration
-- 029) — bewusst per CONCAT angehängt statt überschrieben, damit die
-- bisherige Auswahl eines Administrators für die anderen 6 Mail-Arten
-- erhalten bleibt und "Rechnung per E-Mail" direkt aktiv (mit-archiviert)
-- startet. Eine bereits NULL gesetzte archive_categories-Spalte (Bedeutung
-- auf Domain-Ebene: "alle Kategorien archivieren", siehe
-- SmtpSettings::shouldArchive()) bleibt unverändert NULL — schließt die
-- neue Kategorie dort bereits automatisch mit ein.
USE materialverleih;

INSERT INTO email_templates (template_key, subject, body_html) VALUES
('invoice_pdf', 'Ihre Rechnung {{invoice_number}}',
 '<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie Ihre Rechnung {{invoice_number}} (inkl. XRechnung-XML zur elektronischen Weiterverarbeitung).</p>\n<p>{{location_line}}Leistungszeitraum: {{date_range}}<br>Fällig am: {{due_date}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>');

UPDATE smtp_settings
SET archive_categories = CONCAT(archive_categories, ',invoice_pdf')
WHERE archive_categories IS NOT NULL AND archive_categories NOT LIKE '%invoice_pdf%';
