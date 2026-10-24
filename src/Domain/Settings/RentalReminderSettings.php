<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Firmenweite Einstellung für den automatischen Erinnerungsversand vor
 * Abholung bzw. Rückgabe einer Vermietung (siehe bin/send-rental-reminders.php),
 * Singleton-Zeile analog zu DunningSettings/InvoiceSettings. Der Vorlauf ist
 * bewusst in STUNDEN geführt (nicht Tagen): App\Domain\Rental\Rental::$pickupAt/
 * $returnAt sind echte Datum+Uhrzeit-Werte, und der Cronjob läuft stündlich —
 * eine Tage-Angabe würde die Uhrzeitgenauigkeit dieser Termine verschenken.
 */
final class RentalReminderSettings
{
    public function __construct(
        public int $pickupLeadHours,
        public int $returnLeadHours,
        // Vorlauf (Stunden vor dem Rückgabetermin) sowie Aktiv-Schalter für
        // den automatischen Versand des Kunden-Checklisten-Links (siehe
        // App\Domain\Rental\RentalCustomerChecklistToken). Eigener
        // Aktiv-Schalter (anders als Pickup/Return), da der Linkversand —
        // im Gegensatz zu den reinen Erinnerungstexten — personenbezogene
        // Daten des Kunden offenlegt (einen für ihn klickbaren Link) und
        // manche Betriebe diesen Kanal bewusst ausschließlich manuell durch
        // einen Mitarbeiter teilen lassen möchten.
        public int $checklistLeadHours = 24,
        public bool $checklistAutoSendEnabled = true,
    ) {
    }
}
