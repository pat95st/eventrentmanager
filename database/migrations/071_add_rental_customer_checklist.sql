-- Migration für bereits bestehende Installationen: Kunden-Checkliste zur
-- Vollständigkeitsprüfung beim Abbau — eine öffentliche, token-gesicherte
-- Seite (kein Kundenlogin nötig), über die ein Kunde die für seine
-- Veranstaltung ausgegebenen Artikel selbst abhaken kann (siehe
-- App\Infrastructure\Http\Controllers\CustomerChecklistController).
--
-- rental_customer_checklist_tokens: EIN Link pro Vermietung (UNIQUE auf
-- rental_id). Der Roh-Token wird BEWUSST IM KLARTEXT gespeichert (anders
-- als z. B. bei password_setup_tokens, die nur einen Hash speichern): ein
-- Mitarbeiter muss den Link jederzeit erneut anzeigen/kopieren können, und
-- das System muss ihn automatisch per Cronjob erneut verschicken können
-- (siehe bin/send-rental-reminders.php) — beides ohne dass dafür ein neuer
-- Token erzeugt (und damit ein ggf. vom Kunden bereits geöffneter Link
-- ungültig) werden müsste. Die Sensitivität ist entsprechend gering: der
-- Link gewährt ausschließlich Zugriff auf die Liste der für GENAU DIESE
-- Vermietung ausgegebenen Artikel zum Abhaken, keine weiteren Daten.
--
-- rental_issued_items.confirmed_by_customer_at: wann der Kunde einen
-- einzelnen ausgegebenen Posten über die Checkliste bestätigt hat (NULL =
-- noch nicht bestätigt). Rein für die Kundenansicht selbst, bewusst NICHT
-- in der internen Rückgabe-Checkliste angezeigt.
--
-- rental_reminders.type / rental_reminder_settings: um die dritte
-- automatische Erinnerungsart "customer_checklist" erweitert — der bereits
-- bestehende Cronjob bin/send-rental-reminders.php übernimmt den
-- automatischen Versand mit (siehe dortige Änderungen).
--
-- Vor dem Ausführen ein Backup der Datenbank anlegen.
USE materialverleih;

CREATE TABLE rental_customer_checklist_tokens (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    rental_id  CHAR(36)     NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_customer_checklist_tokens_rental
        FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE,
    CONSTRAINT uq_rental_customer_checklist_tokens_rental UNIQUE (rental_id),
    CONSTRAINT uq_rental_customer_checklist_tokens_token UNIQUE (token)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE rental_issued_items
    ADD COLUMN confirmed_by_customer_at DATETIME NULL AFTER function_test_passed;

ALTER TABLE rental_reminders
    MODIFY COLUMN type ENUM('pickup', 'return', 'customer_checklist') NOT NULL;

ALTER TABLE rental_reminder_settings
    ADD COLUMN checklist_lead_hours INT UNSIGNED NOT NULL DEFAULT 24,
    ADD COLUMN checklist_auto_send_enabled TINYINT(1) NOT NULL DEFAULT 1;

INSERT INTO email_templates (template_key, subject, body_html) VALUES
('rental_customer_checklist', 'Vollständigkeits-Checkliste für {{event_name}}',
 '<p>Guten Tag {{customer_name}},</p>\n<p>bitte prüfen Sie vor dem Abbau über folgenden Link, ob alle für Ihre Veranstaltung ausgegebenen Artikel vollständig vorhanden sind:</p>\n<p><a href="{{link}}">{{link}}</a></p>\n<p>Veranstaltung: {{event_name}}<br>Rückgabetermin: {{return_at}}</p>\n<p>Mit freundlichen Grüßen</p>');
