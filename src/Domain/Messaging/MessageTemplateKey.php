<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * Ein Schlüssel je Stelle im Code, an der eine Nachricht mit einer im
 * Admin-Bereich bearbeitbaren Vorlage (siehe MessageTemplate/
 * MessageTemplateRepositoryInterface) über einen der drei Messenger
 * verschickt wird — bewusst analog zu App\Domain\Mail\EmailTemplateKey,
 * aber NICHT identisch: RentalCustomerChecklist (Link-Versand) ist hier
 * bewusst NICHT abgebildet (siehe Projektdoku, "Bekannte offene Punkte").
 *
 * WICHTIG — Unterschied zwischen den Kanälen bei der Vorlagenpflege:
 *   - Telegram/Signal: MessageTemplate::$bodyText ist ein frei editierbarer
 *     Klartext mit {{platzhalter}} (siehe MessagingTemplateRenderer),
 *     genau wie bei E-Mail-Vorlagen, nur ohne HTML.
 *   - WhatsApp (Meta Cloud API): Business-initiierte Nachrichten außerhalb
 *     eines laufenden 24h-Kundenservice-Fensters MÜSSEN eine bei Meta vorab
 *     genehmigte "Message Template" verwenden — freier Text ist dort nicht
 *     zulässig. MessageTemplate::$metaTemplateName/$metaTemplateLanguage
 *     referenziert diese bereits bei Meta angelegte Vorlage; die
 *     Platzhalter-Reihenfolge, mit der unsere eigenen Variablen auf die
 *     dortigen {{1}}, {{2}}, ... abgebildet werden, ist über
 *     whatsappParameterOrder() FEST im Code hinterlegt (keine generische
 *     Zuordnungsoberfläche, um den Umfang klein zu halten).
 */
enum MessageTemplateKey: string
{
    case RentalPickupReminder = 'rental_pickup_reminder';
    case RentalReturnReminder = 'rental_return_reminder';
    case AdHocMessage = 'ad_hoc_message';
    case InvoiceSent = 'invoice_sent';
    case InvoiceDueReminder = 'invoice_due_reminder';
    case DunningNotice = 'dunning_notice';

    public function label(): string
    {
        return match ($this) {
            self::RentalPickupReminder => 'Erinnerung: Abholung',
            self::RentalReturnReminder => 'Erinnerung: Rückgabe',
            self::AdHocMessage => 'Freie Einzel-Nachricht',
            self::InvoiceSent => 'Rechnung versendet',
            self::InvoiceDueReminder => 'Erinnerung: Rechnung fällig',
            self::DunningNotice => 'Mahnung',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RentalPickupReminder => 'Wird zusätzlich zur E-Mail-Erinnerung automatisch verschickt, wenn der Abholtermin einer Vermietung bevorsteht (siehe Einstellungen -> Erinnerungen) UND der Kunde über diesen Kanal erreichbar ist.',
            self::RentalReturnReminder => 'Wird zusätzlich zur E-Mail-Erinnerung automatisch verschickt, wenn der Rückgabetermin einer Vermietung bevorsteht (siehe Einstellungen -> Erinnerungen) UND der Kunde über diesen Kanal erreichbar ist.',
            self::AdHocMessage => 'Wird verwendet, wenn über die Kundendetailseite spontan eine freie Nachricht an einen Kunden verschickt wird (siehe "Nachricht senden").',
            self::InvoiceSent => 'Wird zusätzlich zur E-Mail verschickt, wenn eine Rechnung per PDF-Mail an den Kunden versendet wird (siehe InvoiceController::emailPdf/SendInvoiceSentViaMessaging).',
            self::InvoiceDueReminder => 'Wird automatisch verschickt, wenn die Fälligkeit einer versendeten, noch offenen Rechnung bevorsteht (siehe Einstellungen -> Rechnungs-Erinnerungen, bin/send-invoice-due-reminders.php). Rein Messenger-basiert, es gibt dafür keine E-Mail-Erinnerung.',
            self::DunningNotice => 'Wird zusätzlich zur E-Mail verschickt, wenn für eine überfällige Rechnung eine Mahnung erstellt wird (manuell oder automatisch, siehe Mahnwesen-Einstellungen, bin/send-dunning-notices.php).',
        };
    }

    /**
     * Ob dieser Schlüssel für den angegebenen Kanal überhaupt eine Vorlage
     * benötigt. AdHocMessage gilt NUR für WhatsApp — dort braucht es zwingend
     * eine bei Meta genehmigte Vorlage, bei Telegram/Signal wird der vom
     * Benutzer eingegebene Freitext direkt verschickt (keine Vorlage nötig).
     */
    public function appliesToChannel(MessagingChannel $channel): bool
    {
        if ($this === self::AdHocMessage) {
            return $channel === MessagingChannel::WhatsApp;
        }

        return true;
    }

    /**
     * Verfügbare Platzhalter für Telegram/Signal-Vorlagen dieses Schlüssels,
     * als [Platzhalter => Erklärung]. Für WhatsApp nicht relevant (siehe
     * Klassenkommentar) und daher hier nicht aufgeführt.
     *
     * @return array<string, string>
     */
    public function availableVariables(): array
    {
        return match ($this) {
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
            self::AdHocMessage => [],
            self::InvoiceSent => [
                'customer_name' => 'Name des Kunden',
                'invoice_number' => 'Rechnungsnummer',
                'due_date' => 'Fälligkeitsdatum, formatiert (TT.MM.JJJJ)',
                'total_gross' => 'Rechnungsbetrag (brutto), formatiert mit Euro-Zeichen',
            ],
            self::InvoiceDueReminder => [
                'customer_name' => 'Name des Kunden',
                'invoice_number' => 'Rechnungsnummer',
                'due_date' => 'Fälligkeitsdatum, formatiert (TT.MM.JJJJ)',
                'total_gross' => 'Rechnungsbetrag (brutto), formatiert mit Euro-Zeichen',
            ],
            self::DunningNotice => [
                'customer_name' => 'Name des Kunden',
                'invoice_number' => 'Rechnungsnummer',
                'dunning_level_name' => 'Name der Mahnstufe',
                'total_due' => 'Offener Gesamtbetrag (Rechnung + Mahngebühr), formatiert mit Euro-Zeichen',
            ],
        };
    }

    /** Klartext-Standardvorlage für Telegram/Signal (siehe MessagingTemplateRenderer::renderPlainText()). */
    public function defaultBodyText(): string
    {
        return match ($this) {
            self::RentalPickupReminder => "Guten Tag {{customer_name}},\n\nwir möchten Sie an die bevorstehende Abholung für folgende Veranstaltung erinnern:\n\nVeranstaltung: {{event_name}}\nAbholtermin: {{pickup_at}}\n{{warehouse_line}}\nMit freundlichen Grüßen",
            self::RentalReturnReminder => "Guten Tag {{customer_name}},\n\nwir möchten Sie an die bevorstehende Rückgabe für folgende Veranstaltung erinnern:\n\nVeranstaltung: {{event_name}}\nRückgabetermin: {{return_at}}\n{{warehouse_line}}\nMit freundlichen Grüßen",
            self::AdHocMessage => '',
            self::InvoiceSent => "Guten Tag {{customer_name}},\n\nwir haben Ihnen die Rechnung {{invoice_number}} über {{total_gross}} per E-Mail zugesendet. Fälligkeitsdatum: {{due_date}}.\n\nMit freundlichen Grüßen",
            self::InvoiceDueReminder => "Guten Tag {{customer_name}},\n\nwir möchten Sie daran erinnern, dass die Rechnung {{invoice_number}} über {{total_gross}} am {{due_date}} fällig wird.\n\nMit freundlichen Grüßen",
            self::DunningNotice => "Guten Tag {{customer_name}},\n\nwir haben Ihnen eine {{dunning_level_name}} zu unserer Rechnung {{invoice_number}} per E-Mail zugesendet. Offener Gesamtbetrag: {{total_due}}.\n\nMit freundlichen Grüßen",
        };
    }

    /**
     * Reihenfolge, in der unsere eigenen Variablen beim WhatsApp-Versand
     * auf die bei Meta genehmigte Vorlage als {{1}}, {{2}}, ... abgebildet
     * werden (siehe Klassenkommentar). AdHocMessage hat genau EINEN
     * Platzhalter: den vom Admin frei eingegebenen Nachrichtentext.
     *
     * @return string[]
     */
    public function whatsappParameterOrder(): array
    {
        return match ($this) {
            self::RentalPickupReminder => ['customer_name', 'event_name', 'pickup_at'],
            self::RentalReturnReminder => ['customer_name', 'event_name', 'return_at'],
            self::AdHocMessage => ['message_text'],
            self::InvoiceSent => ['customer_name', 'invoice_number', 'total_gross', 'due_date'],
            self::InvoiceDueReminder => ['customer_name', 'invoice_number', 'total_gross', 'due_date'],
            self::DunningNotice => ['customer_name', 'invoice_number', 'dunning_level_name', 'total_due'],
        };
    }
}
