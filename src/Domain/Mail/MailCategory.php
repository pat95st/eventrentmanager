<?php

declare(strict_types=1);

namespace App\Domain\Mail;

/**
 * Kategorie einer ausgehenden E-Mail, genutzt zur Steuerung, welche Mails
 * bei gesetzter Archivierungsadresse per BCC archiviert werden (siehe
 * SmtpSettings::shouldArchive(), SmtpMailer::send()). Überschneidet sich
 * bewusst fast, aber nicht ganz mit EmailTemplateKey: TestMail hat keine
 * Vorlage (SendTestMailHandler bleibt hart kodiert), soll aber trotzdem
 * einzeln archivierbar/nicht-archivierbar sein.
 */
enum MailCategory: string
{
    case LoginOtp = 'login_otp';
    case PasswordSetupInvite = 'password_setup_invite';
    case PasswordReset = 'password_reset';
    case OfferPdf = 'offer_pdf';
    case PackingListPdf = 'packing_list_pdf';
    case InvoicePdf = 'invoice_pdf';
    case DunningNotice = 'dunning_notice';
    case TestMail = 'test_mail';
    case ReceiptInboxNotification = 'receipt_inbox_notification';
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
            self::TestMail => 'SMTP-Test-Mail',
            self::ReceiptInboxNotification => 'Neue Beleg-E-Mail im Posteingang',
            self::RentalPickupReminder => 'Erinnerung: Abholung',
            self::RentalReturnReminder => 'Erinnerung: Rückgabe',
            self::RentalCustomerChecklist => 'Kunden-Checkliste (Vollständigkeitsprüfung)',
        };
    }
}
