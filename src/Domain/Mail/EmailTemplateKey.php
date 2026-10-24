<?php

declare(strict_types=1);

namespace App\Domain\Mail;

/**
 * Ein Schlüssel je Stelle im Code, an der eine E-Mail mit einer im
 * Admin-Bereich bearbeitbaren Vorlage (siehe EmailTemplate/
 * EmailTemplateRepositoryInterface) verschickt wird. NICHT dabei: die
 * SMTP-Test-Mail (SendTestMailHandler) — die bleibt bewusst fest im Code,
 * um die SMTP-Konfiguration unabhängig von eventuell fehlerhaften Vorlagen
 * testen zu können.
 */
enum EmailTemplateKey: string
{
    case LoginOtp = 'login_otp';
    case PasswordSetupInvite = 'password_setup_invite';
    case PasswordReset = 'password_reset';
    case OfferPdf = 'offer_pdf';
    case PackingListPdf = 'packing_list_pdf';
    case InvoicePdf = 'invoice_pdf';
    case DunningNotice = 'dunning_notice';
    case RentalPickupReminder = 'rental_pickup_reminder';
    case RentalReturnReminder = 'rental_return_reminder';
    case RentalCustomerChecklist = 'rental_customer_checklist';

    public function label(): string
    {
        return match ($this) {
            self::LoginOtp => 'Anmeldecode (2FA per E-Mail)',
            self::PasswordSetupInvite => 'Neuer Benutzer: Passwort einrichten',
            self::PasswordReset => 'Passwort vergessen',
            self::OfferPdf => 'Angebot per E-Mail',
            self::PackingListPdf => 'Packliste per E-Mail',
            self::InvoicePdf => 'Rechnung per E-Mail',
            self::DunningNotice => 'Mahnung per E-Mail',
            self::RentalPickupReminder => 'Erinnerung: Abholung',
            self::RentalReturnReminder => 'Erinnerung: Rückgabe',
            self::RentalCustomerChecklist => 'Kunden-Checkliste (Vollständigkeitsprüfung)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LoginOtp => 'Wird verschickt, wenn sich ein Benutzer mit E-Mail-Code als zweitem Faktor anmeldet.',
            self::PasswordSetupInvite => 'Wird verschickt, wenn ein Administrator einen neuen Benutzer ohne Startpasswort anlegt.',
            self::PasswordReset => 'Wird verschickt, wenn ein Benutzer über "Passwort vergessen" ein neues Passwort anfordert.',
            self::OfferPdf => 'Wird verschickt, wenn ein Angebot als PDF an einen Kunden gemailt wird.',
            self::PackingListPdf => 'Wird verschickt, wenn die Packliste einer Veranstaltung als PDF an einen Kunden gemailt wird.',
            self::InvoicePdf => 'Wird verschickt, wenn eine Rechnung als PDF (inkl. XRechnung-XML-Anhang bei finalisierten Rechnungen) an einen Kunden gemailt wird.',
            self::DunningNotice => 'Wird verschickt, wenn für eine überfällige Rechnung eine Mahnung als PDF an einen Kunden gemailt wird (manuell oder automatisch, siehe Mahnwesen-Einstellungen).',
            self::RentalPickupReminder => 'Wird automatisch verschickt, wenn der Abholtermin einer Vermietung innerhalb des eingestellten Vorlaufs bevorsteht (siehe Einstellungen -> Erinnerungen).',
            self::RentalReturnReminder => 'Wird automatisch verschickt, wenn der Rückgabetermin einer Vermietung innerhalb des eingestellten Vorlaufs bevorsteht (siehe Einstellungen -> Erinnerungen).',
            self::RentalCustomerChecklist => 'Enthält den Link zur Kunden-Checkliste (Vollständigkeitsprüfung vor dem Abbau) — manuell über die Veranstaltungs-Detailseite verschickt oder automatisch vor dem Rückgabetermin (siehe Einstellungen -> Erinnerungen).',
        };
    }

    /**
     * Verfügbare Platzhalter für diese Vorlage, als [Platzhalter => Erklärung].
     * {{company_name}} ist IMMER zusätzlich verfügbar (siehe EmailTemplateRenderer),
     * wird hier daher nicht extra aufgeführt.
     *
     * @return array<string, string>
     */
    public function availableVariables(): array
    {
        return match ($this) {
            self::LoginOtp => [
                'code' => 'Der sechsstellige Anmeldecode',
                'valid_minutes' => 'Gültigkeitsdauer des Codes in Minuten',
            ],
            self::PasswordSetupInvite, self::PasswordReset => [
                'link' => 'Der Link zum Festlegen des Passworts',
                'valid_hours' => 'Gültigkeitsdauer des Links in Stunden',
            ],
            self::OfferPdf => [
                'customer_name' => 'Name des Kunden (leer, falls kein Kunde verknüpft ist)',
                'location_line' => 'Zeile "Ort: ..." inkl. Zeilenumbruch, oder leer, falls kein Ort hinterlegt ist',
                'date_range' => 'Zeitraum des Angebots, formatiert (TT.MM.JJJJ - TT.MM.JJJJ)',
                'valid_until_line' => 'Zeile "Gültig bis: ..." inkl. Zeilenumbruch, oder leer, falls kein Datum gesetzt ist',
                'note_html' => 'Vom Versender frei eingegebene Anmerkung als HTML-Absatz, oder leer',
            ],
            self::PackingListPdf => [
                'event_name' => 'Name der Veranstaltung',
                'customer_name' => 'Name des Kunden',
                'location_line' => 'Zeile "Ort: ..." inkl. Zeilenumbruch, oder leer, falls kein Ort hinterlegt ist',
                'date_range' => 'Zeitraum der Veranstaltung, formatiert (TT.MM.JJJJ - TT.MM.JJJJ)',
                'note_html' => 'Vom Versender frei eingegebene Anmerkung als HTML-Absatz, oder leer',
            ],
            self::InvoicePdf => [
                'customer_name' => 'Name des Kunden (leer, falls kein Kunde verknüpft ist)',
                'location_line' => 'Zeile "Ort: ..." inkl. Zeilenumbruch, oder leer, falls kein Ort hinterlegt ist',
                'date_range' => 'Leistungszeitraum der Rechnung, formatiert (TT.MM.JJJJ - TT.MM.JJJJ)',
                'invoice_number' => 'Rechnungsnummer',
                'due_date' => 'Fälligkeitsdatum, formatiert (TT.MM.JJJJ)',
                'note_html' => 'Vom Versender frei eingegebene Anmerkung als HTML-Absatz, oder leer',
            ],
            self::DunningNotice => [
                'customer_name' => 'Name des Kunden (leer, falls kein Kunde verknüpft ist)',
                'dunning_number' => 'Mahnungsnummer',
                'dunning_level_name' => 'Name der Mahnstufe (z. B. "Zahlungserinnerung", "1. Mahnung")',
                'invoice_number' => 'Nummer der gemahnten Rechnung',
                'due_date' => 'Ursprüngliches Fälligkeitsdatum der Rechnung, formatiert (TT.MM.JJJJ)',
                'total_due' => 'Gesamtbetrag inkl. Mahngebühr, formatiert (z. B. "123,45 €")',
            ],
            self::RentalPickupReminder => [
                'customer_name' => 'Name des Kunden',
                'event_name' => 'Name der Veranstaltung',
                'pickup_at' => 'Abholtermin, formatiert (TT.MM.JJJJ, HH:MM Uhr)',
                'warehouse_line' => 'Zeile "Abholort: ..." mit Name und Adresse des Lagerorts inkl. Zeilenumbruch, oder leer, falls kein Lagerort hinterlegt ist',
            ],
            self::RentalReturnReminder => [
                'customer_name' => 'Name des Kunden',
                'event_name' => 'Name der Veranstaltung',
                'return_at' => 'Rückgabetermin, formatiert (TT.MM.JJJJ, HH:MM Uhr)',
                'warehouse_line' => 'Zeile "Rückgabeort: ..." mit Name und Adresse des Lagerorts inkl. Zeilenumbruch, oder leer, falls kein Lagerort hinterlegt ist',
            ],
            self::RentalCustomerChecklist => [
                'customer_name' => 'Name des Kunden',
                'event_name' => 'Name der Veranstaltung',
                'return_at' => 'Rückgabetermin, formatiert (TT.MM.JJJJ, HH:MM Uhr), oder leer, falls kein Termin hinterlegt ist',
                'link' => 'Der Link zur Kunden-Checkliste',
            ],
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::LoginOtp => 'Ihr Anmeldecode für {{company_name}}',
            self::PasswordSetupInvite => 'Ihr Zugang zur {{company_name}}-Verwaltung',
            self::PasswordReset => 'Passwort zurücksetzen',
            self::OfferPdf => 'Ihr Angebot',
            self::PackingListPdf => 'Packliste für Ihre Veranstaltung',
            self::InvoicePdf => 'Ihre Rechnung {{invoice_number}}',
            self::DunningNotice => '{{dunning_level_name}}: Rechnung {{invoice_number}}',
            self::RentalPickupReminder => 'Erinnerung: Abholung für {{event_name}}',
            self::RentalReturnReminder => 'Erinnerung: Rückgabe für {{event_name}}',
            self::RentalCustomerChecklist => 'Vollständigkeits-Checkliste für {{event_name}}',
        };
    }

    public function defaultBodyHtml(): string
    {
        return match ($this) {
            self::LoginOtp => "<p>Ihr Anmeldecode lautet: <strong>{{code}}</strong></p>\n<p>Der Code ist {{valid_minutes}} Minuten gültig. Falls Sie diesen Code nicht angefordert haben, ignorieren Sie diese E-Mail.</p>",
            self::PasswordSetupInvite => "<p>Hallo,</p>\n<p>für Sie wurde ein Benutzerkonto in der {{company_name}}-Verwaltung angelegt. Bitte legen Sie über folgenden Link Ihr Passwort fest:</p>\n<p><a href=\"{{link}}\">{{link}}</a></p>\n<p>Der Link ist {{valid_hours}} Stunden gültig.</p>",
            self::PasswordReset => "<p>Hallo,</p>\n<p>für Ihr Konto in der {{company_name}}-Verwaltung wurde ein neues Passwort angefordert. Über folgenden Link können Sie ein neues Passwort festlegen:</p>\n<p><a href=\"{{link}}\">{{link}}</a></p>\n<p>Der Link ist {{valid_hours}} Stunden gültig. Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren — es ändert sich nichts an Ihrem Konto.</p>",
            self::OfferPdf => "<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie unser Angebot.</p>\n<p>{{location_line}}Zeitraum: {{date_range}}<br>{{valid_until_line}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>",
            self::PackingListPdf => "<p>Guten Tag,</p>\n<p>im Anhang finden Sie die Packliste für folgende Veranstaltung:</p>\n<p>Veranstaltung: {{event_name}}<br>Kunde: {{customer_name}}<br>{{location_line}}Zeitraum: {{date_range}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>",
            self::InvoicePdf => "<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie Ihre Rechnung {{invoice_number}} (inkl. XRechnung-XML zur elektronischen Weiterverarbeitung).</p>\n<p>{{location_line}}Leistungszeitraum: {{date_range}}<br>Fällig am: {{due_date}}</p>\n{{note_html}}\n<p>Mit freundlichen Grüßen</p>",
            self::DunningNotice => "<p>Guten Tag{{customer_name}},</p>\n<p>im Anhang finden Sie eine {{dunning_level_name}} zu unserer Rechnung {{invoice_number}}, die am {{due_date}} fällig war.</p>\n<p>Offener Gesamtbetrag: {{total_due}}</p>\n<p>Bitte begleichen Sie den Betrag umgehend. Sollte dies bereits geschehen sein, betrachten Sie diese E-Mail als gegenstandslos.</p>\n<p>Mit freundlichen Grüßen</p>",
            self::RentalPickupReminder => "<p>Guten Tag {{customer_name}},</p>\n<p>wir möchten Sie an die bevorstehende Abholung für folgende Veranstaltung erinnern:</p>\n<p>Veranstaltung: {{event_name}}<br>Abholtermin: {{pickup_at}}<br>{{warehouse_line}}</p>\n<p>Mit freundlichen Grüßen</p>",
            self::RentalReturnReminder => "<p>Guten Tag {{customer_name}},</p>\n<p>wir möchten Sie an die bevorstehende Rückgabe für folgende Veranstaltung erinnern:</p>\n<p>Veranstaltung: {{event_name}}<br>Rückgabetermin: {{return_at}}<br>{{warehouse_line}}</p>\n<p>Mit freundlichen Grüßen</p>",
            self::RentalCustomerChecklist => "<p>Guten Tag {{customer_name}},</p>\n<p>bitte prüfen Sie vor dem Abbau über folgenden Link, ob alle für Ihre Veranstaltung ausgegebenen Artikel vollständig vorhanden sind:</p>\n<p><a href=\"{{link}}\">{{link}}</a></p>\n<p>Veranstaltung: {{event_name}}<br>Rückgabetermin: {{return_at}}</p>\n<p>Mit freundlichen Grüßen</p>",
        };
    }
}
