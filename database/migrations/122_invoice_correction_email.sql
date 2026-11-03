-- Migration 122: Rechnungskorrekturen per E-Mail versenden (Nutzerwunsch).
-- Merkt sich, wann und an wen eine Korrektur zuletzt verschickt wurde, und
-- legt die bearbeitbare E-Mail-Vorlage an (Einstellungen › E-Mail-Vorlagen).

ALTER TABLE invoice_corrections
    ADD COLUMN sent_at DATETIME     NULL AFTER settled_at,
    ADD COLUMN sent_to VARCHAR(255) NULL AFTER sent_at;

INSERT IGNORE INTO email_templates (template_key, subject, body_html) VALUES
('invoice_correction', '{{document_title}} {{correction_number}} zu Rechnung {{invoice_number}}',
 '<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie unsere {{document_title}} {{correction_number}} zur Rechnung {{invoice_number}} vom {{invoice_date}}.</p>\n<p>Grund: {{reason}}<br>Betrag: {{amount}}</p>\n<p>{{payment_line}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>');
