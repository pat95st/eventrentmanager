#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Application\Invoice\Command\CreateInvoiceDueReminder;
use App\Application\Invoice\Command\SendInvoiceDueReminderViaMessaging;
use App\Application\Messaging\MessagingSendResult;
use App\Application\Settings\Query\GetInvoiceDueReminderSettings;
use App\Domain\Invoice\Invoice;

// Cron-Skript für die automatische Fälligkeits-Erinnerung einer versendeten,
// noch offenen Rechnung: läuft NICHT automatisch im Web-Request (analog
// bin/send-dunning-notices.php — siehe dortige Kommentare für die
// Begründung des Bootstrap-Zugriffsmusters über Top-Level-`require`). Muss
// per Cronjob eingerichtet werden (empfohlen: einmal täglich, z. B. morgens).
//
// AUSSCHLIESSLICH per Messenger (WhatsApp Business/Telegram/Signal, siehe
// App\Domain\Messaging) — es gibt dafür bewusst KEINE E-Mail-Erinnerung
// (siehe Migration 086-Kommentar), anders als bei Abholung/Rückgabe oder
// Mahnwesen. Hat ein Kunde keinen nutzbaren Messenger-Kanal, wird
// übersprungen (kein E-Mail-Fallback).
//
// Ablauf je fällig werdender Rechnung (Status "Sent", due_date zwischen
// heute und heute + lead_days — siehe InvoiceRepositoryInterface::findDueSoon()):
//   1. Falls für diese Rechnung bereits eine Fälligkeits-Erinnerung existiert
//      (InvoiceDueReminderRepositoryInterface::existsForInvoice), wird
//      übersprungen — pro Rechnung wird nur EINMAL automatisch erinnert
//      (robust gegen mehrfache Cronjob-Läufe innerhalb des
//      Vorlauf-Zeitfensters).
//   2. SendInvoiceDueReminderViaMessaging wird dispatcht. Hat der Kunde
//      keinen nutzbaren Messenger-Kanal (kein Versand über irgendeinen
//      Kanal), wird übersprungen UND KEIN Log-Eintrag angelegt, damit ein
//      späterer Lauf es erneut versuchen kann, falls der Kunde
//      zwischenzeitlich z. B. eine Telegram-Chat-ID hinterlegt bekommt.
//   3. Sonst: ein Log-Eintrag (InvoiceDueReminder) wird angelegt, damit
//      dieselbe Rechnung nie zweimal automatisch erinnert wird. Pro
//      Rechnung einzeln try/catch-abgesichert, damit ein Fehler bei einer
//      Rechnung nicht den gesamten Lauf abbricht.
/** @var \App\Infrastructure\Http\Router $router Ungenutzt — wir brauchen nur die lokalen Variablen, die bootstrap.php nebenbei definiert. */
$router = require __DIR__ . '/../src/bootstrap.php';

if (!isset($commandBus) || !isset($queryBus)) {
    fwrite(STDERR, "Fehler: \$commandBus/\$queryBus wurden nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

if (!isset($invoiceRepository)) {
    fwrite(STDERR, "Fehler: \$invoiceRepository wurde nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

if (!isset($invoiceDueReminderRepository)) {
    fwrite(STDERR, "Fehler: \$invoiceDueReminderRepository wurde nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

echo "Materialverleih — Automatische Fälligkeits-Erinnerung (nur Messenger)\n";
echo str_repeat('-', 59) . "\n";
echo 'Gestartet: ' . date('Y-m-d H:i:s') . "\n\n";

/** @var \App\Domain\Settings\InvoiceDueReminderSettings $settings */
$settings = $queryBus->ask(new GetInvoiceDueReminderSettings());

if (!$settings->enabled) {
    echo "Automatischer Fälligkeits-Erinnerungsversand deaktiviert (siehe Einstellungen -> Rechnungs-Erinnerungen -> \"Automatisch verschicken\").\n";
    exit(0);
}

/** @var Invoice[] $dueSoonInvoices */
$dueSoonInvoices = $invoiceRepository->findDueSoon($settings->leadDays);

echo 'Demnächst fällige Rechnungen gefunden: ' . count($dueSoonInvoices) . "\n\n";

$createdCount = 0;
$messagedCount = 0;
$skippedDuplicate = 0;
$skippedNoChannel = 0;
$errorCount = 0;

foreach ($dueSoonInvoices as $invoice) {
    try {
        if ($invoiceDueReminderRepository->existsForInvoice($invoice->id)) {
            $skippedDuplicate++;

            continue;
        }

        $messagingResult = $commandBus->dispatch(new SendInvoiceDueReminderViaMessaging($invoice->id));
        assert($messagingResult instanceof MessagingSendResult);

        if (count($messagingResult->sentChannels) === 0) {
            $skippedNoChannel++;

            foreach ($messagingResult->failedChannels as $channelValue => $errorMessage) {
                $errorCount++;
                fwrite(STDERR, "Fehler bei Rechnung {$invoice->id} (Fälligkeits-Erinnerung, Messenger \"{$channelValue}\"): {$errorMessage}\n");
            }

            continue;
        }

        $commandBus->dispatch(new CreateInvoiceDueReminder(invoiceId: $invoice->id));
        $createdCount++;
        $messagedCount += count($messagingResult->sentChannels);

        foreach ($messagingResult->sentChannels as $channel) {
            echo "Rechnung {$invoice->invoiceNumber}: Fälligkeits-Erinnerung per {$channel->label()} verschickt.\n";
        }
        foreach ($messagingResult->failedChannels as $channelValue => $errorMessage) {
            $errorCount++;
            fwrite(STDERR, "Fehler bei Rechnung {$invoice->id} (Fälligkeits-Erinnerung, Messenger \"{$channelValue}\"): {$errorMessage}\n");
        }
    } catch (Throwable $e) {
        $errorCount++;
        fwrite(STDERR, "Fehler bei Rechnung {$invoice->id}: " . $e->getMessage() . "\n");
    }
}

echo "\n";
echo "Erinnerungen erstellt:                    {$createdCount}\n";
echo "Davon per Messenger (Summe über alle Kanäle): {$messagedCount}\n";
echo "Übersprungen (bereits erinnert):          {$skippedDuplicate}\n";
echo "Übersprungen (kein Messenger-Kanal nutzbar): {$skippedNoChannel}\n";
echo "Fehler:                                   {$errorCount}\n";
echo "\nFertig: " . date('Y-m-d H:i:s') . "\n";

exit($errorCount > 0 ? 4 : 0);
