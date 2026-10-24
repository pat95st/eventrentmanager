<?php

declare(strict_types=1);

namespace App\Domain\Rental;

use DateTimeImmutable;

/**
 * Log-Eintrag für eine tatsächlich verschickte Abhol-/Rückgabe-Erinnerung
 * (siehe bin/send-rental-reminders.php). Dient AUSSCHLIESSLICH der
 * Duplikat-Vermeidung (siehe RentalReminderRepositoryInterface::existsForRentalAndType())
 * bei mehrfachem Cronjob-Lauf innerhalb des konfigurierten Vorlauf-Zeitfensters
 * — kein eigenständiges, in der Oberfläche bearbeitbares Dokument wie
 * z. B. DunningNotice.
 */
final class RentalReminder
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly RentalReminderType $type,
        public readonly DateTimeImmutable $sentAt,
    ) {
    }
}
