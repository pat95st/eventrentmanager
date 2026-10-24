#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Application\Messaging\MessagingSendResult;
use App\Application\Rental\Command\CreateRentalReminder;
use App\Application\Rental\Command\EmailRentalCustomerChecklist;
use App\Application\Rental\Command\EmailRentalPickupReminder;
use App\Application\Rental\Command\EmailRentalReturnReminder;
use App\Application\Rental\Command\SendRentalReminderViaMessaging;
use App\Application\Rental\Query\GetRental;
use App\Application\Settings\Query\GetRentalReminderSettings;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalReminderType;

// Cron-Skript für automatische Abhol-/Rückgabe-Erinnerungen UND den
// automatischen Versand des Kunden-Checklisten-Links: läuft NICHT
// automatisch im Web-Request (analog bin/send-dunning-notices.php — siehe
// dortige Kommentare für die Begründung des Bootstrap-Zugriffsmusters über
// Top-Level-`require`). Muss per Cronjob eingerichtet werden (empfohlen:
// STÜNDLICH, da der Vorlauf in Stunden konfiguriert wird, siehe
// App\Domain\Settings\RentalReminderSettings).
//
// Ablauf je Erinnerungsart (Abholung/Rückgabe/Kunden-Checkliste) getrennt:
//   1. Alle Vermietungen ermitteln, deren Abhol- bzw. Rückgabetermin
//      (pickup_at/return_at — die Checkliste nutzt denselben Rückgabetermin
//      wie die Rückgabe-Erinnerung, siehe RentalRepositoryInterface::
//      findChecklistsDueForReminder()) noch in der Zukunft liegt und
//      innerhalb des konfigurierten Vorlaufs fällig wird — Vermietungen OHNE
//      gesetzten Termin werden nie geliefert, da kein Erinnerungszeitpunkt
//      bekannt ist.
//   2. Falls für diese Vermietung UND genau diese Art bereits eine
//      Erinnerung existiert (RentalReminderRepositoryInterface::
//      existsForRentalAndType), wird übersprungen — jede Art wird pro
//      Vermietung nur einmal automatisch verschickt (robust gegen
//      mehrfache Cronjob-Läufe innerhalb des Vorlauf-Zeitfensters).
//   3. Bei Abholung/Rückgabe wird NEBEN der E-Mail (falls Kunden-E-Mail
//      hinterlegt) ZUSÄTZLICH versucht, über jeden aktivierten UND
//      konfigurierten Messenger zu verschicken, für den der Kunde die
//      nötigen Kontaktdaten hinterlegt hat — siehe
//      SendRentalReminderViaMessagingHandler (WhatsApp Business/Telegram/
//      Signal, siehe App\Domain\Messaging). Hat ein Kunde WEDER E-Mail
//      NOCH einen nutzbaren Messenger-Kanal, wird übersprungen (kein
//      Postversand-Fallback). Die Kunden-Checkliste (Schritt 4) bleibt
//      bewusst E-Mail-only.
//   4. Sonst: CreateRentalReminder (Log-Eintrag zur Duplikat-Vermeidung) —
//      EINMAL pro Vermietung+Art, unabhängig davon, über wie viele Kanäle
//      tatsächlich verschickt wurde. Pro Vermietung einzeln
//      try/catch-abgesichert (fängt u. a. eine fehlende Basis-URL ab, siehe
//      RentalCustomerChecklistUrlBuilder), damit ein Fehler bei einer
//      Vermietung nicht den gesamten Lauf abbricht.
/** @var \App\Infrastructure\Http\Router $router Ungenutzt — wir brauchen nur die lokalen Variablen, die bootstrap.php nebenbei definiert. */
$router = require __DIR__ . '/../src/bootstrap.php';

if (!isset($commandBus) || !isset($queryBus)) {
    fwrite(STDERR, "Fehler: \$commandBus/\$queryBus wurden nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

if (!isset($rentalRepository)) {
    fwrite(STDERR, "Fehler: \$rentalRepository wurde nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

if (!isset($rentalReminderRepository)) {
    fwrite(STDERR, "Fehler: \$rentalReminderRepository wurde nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

echo "Materialverleih — Automatische Abhol-/Rückgabe-Erinnerungen (E-Mail + Messenger) + Kunden-Checkliste\n";
echo str_repeat('-', 60) . "\n";
echo 'Gestartet: ' . date('Y-m-d H:i:s') . "\n\n";

/** @var \App\Domain\Settings\RentalReminderSettings $settings */
$settings = $queryBus->ask(new GetRentalReminderSettings());
$now = new DateTimeImmutable();

$totalCreated = 0;
$totalEmailed = 0;
$totalMessaged = 0;
$totalSkippedDuplicate = 0;
$totalSkippedNoEmail = 0;
$totalErrors = 0;

/**
 * @param Rental[] $rentals
 */
function processReminders(
    array $rentals,
    RentalReminderType $type,
    string $label,
    \App\Application\Command\CommandBus $commandBus,
    \App\Application\Query\QueryBus $queryBus,
    \App\Domain\Rental\RentalReminderRepositoryInterface $rentalReminderRepository,
    int &$totalCreated,
    int &$totalEmailed,
    int &$totalSkippedDuplicate,
    int &$totalSkippedNoEmail,
    int &$totalErrors,
    bool $alsoViaMessaging = false,
    int &$totalMessaged = 0,
): void {
    echo "Fällige {$label}-Erinnerungen: " . count($rentals) . " Vermietung(en).\n";

    foreach ($rentals as $rental) {
        try {
            if ($rentalReminderRepository->existsForRentalAndType($rental->id, $type)) {
                $totalSkippedDuplicate++;

                continue;
            }

            /** @var \App\Application\Rental\Query\RentalDetail $detail */
            $detail = $queryBus->ask(new GetRental($rental->id));
            $recipientEmail = $detail->customer->email;
            $hasEmail = $recipientEmail !== null && trim($recipientEmail) !== '';

            // Messenger-Versand VOR dem no-contact-Check ermitteln (nicht
            // erst versenden), damit bei "weder E-Mail noch Messenger"
            // korrekt übersprungen wird, statt eine leere Erinnerung
            // anzulegen.
            $messagingResult = null;
            if ($alsoViaMessaging) {
                $messagingResult = $commandBus->dispatch(new SendRentalReminderViaMessaging(rentalId: $rental->id, type: $type));
                assert($messagingResult instanceof MessagingSendResult);
            }
            $hasMessaging = $messagingResult !== null && count($messagingResult->sentChannels) > 0;

            if (!$hasEmail && !$hasMessaging) {
                $totalSkippedNoEmail++;

                continue;
            }

            $commandBus->dispatch(new CreateRentalReminder(rentalId: $rental->id, type: $type));
            $totalCreated++;

            if ($hasEmail) {
                match ($type) {
                    RentalReminderType::Pickup => $commandBus->dispatch(new EmailRentalPickupReminder(rentalId: $rental->id, recipientEmail: $recipientEmail)),
                    RentalReminderType::Return => $commandBus->dispatch(new EmailRentalReturnReminder(rentalId: $rental->id, recipientEmail: $recipientEmail)),
                    RentalReminderType::CustomerChecklist => $commandBus->dispatch(new EmailRentalCustomerChecklist(rentalId: $rental->id, recipientEmail: $recipientEmail)),
                };
                $totalEmailed++;
                echo "Veranstaltung \"{$rental->eventName}\": {$label}-Erinnerung an {$recipientEmail} verschickt.\n";
            }

            if ($messagingResult !== null) {
                $totalMessaged += count($messagingResult->sentChannels);
                foreach ($messagingResult->sentChannels as $channel) {
                    echo "Veranstaltung \"{$rental->eventName}\": {$label}-Erinnerung zusätzlich per {$channel->label()} verschickt.\n";
                }
                foreach ($messagingResult->failedChannels as $channelValue => $errorMessage) {
                    $totalErrors++;
                    fwrite(STDERR, "Fehler bei Vermietung {$rental->id} ({$label}, Messenger \"{$channelValue}\"): {$errorMessage}\n");
                }
            }
        } catch (Throwable $e) {
            $totalErrors++;
            fwrite(STDERR, "Fehler bei Vermietung {$rental->id} ({$label}): " . $e->getMessage() . "\n");
        }
    }
}

$pickupRentals = $rentalRepository->findPickupsDueForReminder($now, $settings->pickupLeadHours);
processReminders(
    $pickupRentals,
    RentalReminderType::Pickup,
    'Abholung',
    $commandBus,
    $queryBus,
    $rentalReminderRepository,
    $totalCreated,
    $totalEmailed,
    $totalSkippedDuplicate,
    $totalSkippedNoEmail,
    $totalErrors,
    alsoViaMessaging: true,
    totalMessaged: $totalMessaged,
);

echo "\n";

$returnRentals = $rentalRepository->findReturnsDueForReminder($now, $settings->returnLeadHours);
processReminders(
    $returnRentals,
    RentalReminderType::Return,
    'Rückgabe',
    $commandBus,
    $queryBus,
    $rentalReminderRepository,
    $totalCreated,
    $totalEmailed,
    $totalSkippedDuplicate,
    $totalSkippedNoEmail,
    $totalErrors,
    alsoViaMessaging: true,
    totalMessaged: $totalMessaged,
);

echo "\n";

if ($settings->checklistAutoSendEnabled) {
    $checklistRentals = $rentalRepository->findChecklistsDueForReminder($now, $settings->checklistLeadHours);
    processReminders(
        $checklistRentals,
        RentalReminderType::CustomerChecklist,
        'Kunden-Checkliste',
        $commandBus,
        $queryBus,
        $rentalReminderRepository,
        $totalCreated,
        $totalEmailed,
        $totalSkippedDuplicate,
        $totalSkippedNoEmail,
        $totalErrors,
    );
} else {
    echo "Automatischer Versand der Kunden-Checkliste ist deaktiviert (siehe Einstellungen -> Erinnerungen), übersprungen.\n";
}

echo "\n";
echo "Erinnerungen erstellt:                 {$totalCreated}\n";
echo "Davon per E-Mail verschickt:           {$totalEmailed}\n";
echo "Davon zusätzlich per Messenger (Summe über alle Kanäle): {$totalMessaged}\n";
echo "Übersprungen (bereits erinnert):       {$totalSkippedDuplicate}\n";
echo "Übersprungen (kein Kontaktweg nutzbar): {$totalSkippedNoEmail}\n";
echo "Fehler:                                {$totalErrors}\n";
echo "\nFertig: " . date('Y-m-d H:i:s') . "\n";

exit($totalErrors > 0 ? 4 : 0);
