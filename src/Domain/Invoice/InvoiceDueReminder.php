<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use DateTimeImmutable;

/**
 * Log-Eintrag für eine tatsächlich verschickte Fälligkeits-Erinnerung (siehe
 * bin/send-invoice-due-reminders.php). Dient AUSSCHLIESSLICH der
 * Duplikat-Vermeidung (siehe InvoiceDueReminderRepositoryInterface::existsForInvoice())
 * bei mehrfachem Cronjob-Lauf innerhalb des konfigurierten Vorlauf-Zeitfensters
 * — kein eigenständiges, in der Oberfläche bearbeitbares Dokument, analog
 * App\Domain\Rental\RentalReminder.
 */
final class InvoiceDueReminder
{
    public function __construct(
        public readonly string $id,
        public readonly string $invoiceId,
        public readonly DateTimeImmutable $sentAt,
    ) {
    }
}
