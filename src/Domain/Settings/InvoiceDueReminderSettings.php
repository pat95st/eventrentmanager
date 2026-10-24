<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Firmenweite Einstellung für die automatische Fälligkeits-Erinnerung einer
 * versendeten, noch offenen Rechnung (siehe bin/send-invoice-due-reminders.php),
 * Singleton-Zeile analog zu RentalReminderSettings/DunningSettings. Der
 * Vorlauf ist bewusst in TAGEN geführt (nicht Stunden wie bei
 * RentalReminderSettings) — App\Domain\Invoice\Invoice::$dueDate ist ein
 * reines Datum ohne Uhrzeit, und der Cronjob läuft täglich.
 *
 * $enabled ist standardmäßig false: anders als die Abhol-/Rückgabe-
 * Erinnerung ist dies ein rein zusätzlicher, automatischer Kundenkontakt
 * ohne E-Mail-Gegenstück, den ein Betrieb bewusst einschalten muss.
 */
final class InvoiceDueReminderSettings
{
    public function __construct(
        public bool $enabled = false,
        public int $leadDays = 3,
    ) {
    }
}
