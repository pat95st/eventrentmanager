-- Migration für bereits bestehende Installationen: admin-bearbeitbare
-- Vorlagen (Betreff + HTML-Inhalt) für alle vom System versendeten E-Mails
-- (siehe App\Domain\Mail\EmailTemplateKey für die vollständige Liste der
-- Schlüssel sowie App\Application\Mail\EmailTemplateRenderer für die
-- Platzhalter-Ersetzung beim Versand). Bewusst NICHT dabei: die SMTP-Test-
-- Mail (SendTestMailHandler) — die bleibt fest im Code, damit die SMTP-
-- Konfiguration unabhängig von eventuell fehlerhaften Vorlagen getestet
-- werden kann.
--
-- Jede Zeile wird direkt mit den bisherigen, fest im Code verankerten
-- Standardtexten (jetzt als HTML) vorbefüllt, damit bestehende
-- Installationen nach dieser Migration exakt dieselben Mails wie zuvor
-- verschicken — bis ein Administrator eine Vorlage im Einstellungsbereich
-- anpasst.
USE materialverleih;

CREATE TABLE email_templates (
    template_key VARCHAR(64)   NOT NULL PRIMARY KEY,
    subject      VARCHAR(255)  NOT NULL,
    body_html    MEDIUMTEXT    NOT NULL,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO email_templates (template_key, subject, body_html) VALUES
('login_otp', 'Ihr Anmeldecode für {{company_name}}',
 '<p>Ihr Anmeldecode lautet: <strong>{{code}}</strong></p>\n<p>Der Code ist {{valid_minutes}} Minuten gültig. Falls Sie diesen Code nicht angefordert haben, ignorieren Sie diese E-Mail.</p>'),
('password_setup_invite', 'Ihr Zugang zur {{company_name}}-Verwaltung',
 '<p>Hallo,</p>\n<p>für Sie wurde ein Benutzerkonto in der {{company_name}}-Verwaltung angelegt. Bitte legen Sie über folgenden Link Ihr Passwort fest:</p>\n<p><a href="{{link}}">{{link}}</a></p>\n<p>Der Link ist {{valid_hours}} Stunden gültig.</p>'),
('password_reset', 'Passwort zurücksetzen',
 '<p>Hallo,</p>\n<p>für Ihr Konto in der {{company_name}}-Verwaltung wurde ein neues Passwort angefordert. Über folgenden Link können Sie ein neues Passwort festlegen:</p>\n<p><a href="{{link}}">{{link}}</a></p>\n<p>Der Link ist {{valid_hours}} Stunden gültig. Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren — es ändert sich nichts an Ihrem Konto.</p>'),
('offer_pdf', 'Ihr Angebot',
 '<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie unser Angebot.</p>\n<p>{{location_line}}Zeitraum: {{date_range}}<br>{{valid_until_line}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>'),
('packing_list_pdf', 'Packliste für Ihre Veranstaltung',
 '<p>Guten Tag,</p>\n<p>im Anhang finden Sie die Packliste für folgende Veranstaltung:</p>\n<p>Veranstaltung: {{event_name}}<br>Kunde: {{customer_name}}<br>{{location_line}}Zeitraum: {{date_range}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>');
