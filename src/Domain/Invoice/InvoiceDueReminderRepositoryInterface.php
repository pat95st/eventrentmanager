<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

interface InvoiceDueReminderRepositoryInterface
{
    /**
     * Für die Duplikatsprüfung beim automatischen Versand (siehe
     * bin/send-invoice-due-reminders.php): true, wenn für diese Rechnung
     * bereits eine Fälligkeits-Erinnerung verschickt wurde, damit diese nie
     * zweimal automatisch verschickt wird.
     */
    public function existsForInvoice(string $invoiceId): bool;

    public function create(InvoiceDueReminder $reminder): void;
}
