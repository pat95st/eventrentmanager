#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Application\Dunning\Command\CreateDunningNotice;
use App\Application\Dunning\Command\EmailDunningNotice;
use App\Application\Dunning\Query\GetDunningSettings;
use App\Application\Dunning\Query\ListDunningLevels;
use App\Application\Invoice\Query\GetInvoice;
use App\Domain\Dunning\DunningLevel;
use App\Domain\Invoice\Invoice;

// Cron-Skript für das automatische Mahnwesen: läuft NICHT automatisch im
// Web-Request (analog bin/import-receipt-emails.php — siehe dortige
// Kommentare für die Begründung des Bootstrap-Zugriffsmusters über
// Top-Level-`require`, das $commandBus/$queryBus in diesen Dateigeltungs-
// bereich importiert). Muss per Cronjob eingerichtet werden (empfohlen:
// einmal täglich, z. B. nachts).
//
// Ablauf je überfälliger Rechnung (Status "Sent", due_date in der
// Vergangenheit — siehe InvoiceRepositoryInterface::findOverdue()):
//   1. Höchste passende Mahnstufe ermitteln: unter allen Mahnstufen, deren
//      Fristtage (daysAfterDue) die tatsächlich seit Fälligkeit verstrichenen
//      Tage nicht überschreiten, wird die mit der HÖCHSTEN Fristtage-Zahl
//      gewählt (= die "am weitesten fortgeschrittene" passende Stufe).
//   2. Falls für diese Rechnung UND genau diese Stufe bereits eine Mahnung
//      existiert (DunningNoticeRepositoryInterface::existsForInvoiceAndLevel),
//      wird übersprungen — jede Stufe wird pro Rechnung nur einmal
//      automatisch verschickt.
//   3. Falls der Kunde keine E-Mail-Adresse hinterlegt hat, wird
//      übersprungen (kein Postversand-Fallback in dieser Version).
//   4. Sonst: CreateDunningNotice (createdBy: 'automatic') gefolgt von
//      EmailDunningNotice. Pro Rechnung einzeln try/catch-abgesichert,
//      damit ein Fehler bei einer Rechnung nicht den gesamten Lauf abbricht.
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

if (!isset($dunningNoticeRepository)) {
    fwrite(STDERR, "Fehler: \$dunningNoticeRepository wurde nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

echo "Materialverleih — Automatisches Mahnwesen\n";
echo str_repeat('-', 43) . "\n";
echo 'Gestartet: ' . date('Y-m-d H:i:s') . "\n\n";

/** @var \App\Domain\Dunning\DunningSettings $settings */
$settings = $queryBus->ask(new GetDunningSettings());

if (!$settings->autoSendEnabled) {
    echo "Automatischer Mahnungsversand deaktiviert (siehe Einstellungen -> Mahnwesen -> \"Automatisch verschicken\").\n";
    exit(0);
}

/** @var DunningLevel[] $levels */
$levels = $queryBus->ask(new ListDunningLevels());

if ($levels === []) {
    echo "Keine Mahnstufen konfiguriert — nichts zu tun.\n";
    exit(0);
}

// Bereits aufsteigend nach daysAfterDue sortiert (siehe
// DunningLevelRepositoryInterface::findAllOrdered()); für die
// "höchste passende Stufe"-Auswahl unten reicht es daher, die Liste einmal
// von hinten nach vorn zu durchsuchen.

/** @var Invoice[] $overdueInvoices */
$overdueInvoices = $invoiceRepository->findOverdue();

echo 'Überfällige Rechnungen gefunden: ' . count($overdueInvoices) . "\n\n";

$today = new DateTimeImmutable('today');
$createdCount = 0;
$emailedCount = 0;
$skippedNoLevel = 0;
$skippedDuplicate = 0;
$skippedNoEmail = 0;
$errorCount = 0;

foreach ($overdueInvoices as $invoice) {
    try {
        if ($invoice->dueDate === null) {
            continue;
        }

        $daysOverdue = (int) $today->diff($invoice->dueDate)->days;

        $applicableLevel = null;
        foreach (array_reverse($levels) as $level) {
            if ($level->daysAfterDue <= $daysOverdue) {
                $applicableLevel = $level;
                break;
            }
        }

        if ($applicableLevel === null) {
            $skippedNoLevel++;
            continue;
        }

        if ($dunningNoticeRepository->existsForInvoiceAndLevel($invoice->id, $applicableLevel->id)) {
            $skippedDuplicate++;
            continue;
        }

        /** @var \App\Application\Invoice\Query\InvoiceDetail $detail */
        $detail = $queryBus->ask(new GetInvoice($invoice->id));
        $recipientEmail = $detail->customer?->email;

        if ($recipientEmail === null || trim($recipientEmail) === '') {
            $skippedNoEmail++;
            continue;
        }

        $noticeId = $commandBus->dispatch(new CreateDunningNotice(
            invoiceId: $invoice->id,
            dunningLevelId: $applicableLevel->id,
            createdBy: 'automatic',
        ));
        $createdCount++;

        $commandBus->dispatch(new EmailDunningNotice(
            dunningNoticeId: $noticeId,
            recipientEmail: $recipientEmail,
        ));
        $emailedCount++;

        echo "Rechnung {$invoice->invoiceNumber}: Mahnung \"{$applicableLevel->name}\" erstellt und an {$recipientEmail} verschickt.\n";
    } catch (Throwable $e) {
        $errorCount++;
        fwrite(STDERR, "Fehler bei Rechnung {$invoice->id}: " . $e->getMessage() . "\n");
    }
}

echo "\n";
echo "Mahnungen erstellt:                {$createdCount}\n";
echo "Davon per E-Mail verschickt:       {$emailedCount}\n";
echo "Übersprungen (keine Stufe fällig): {$skippedNoLevel}\n";
echo "Übersprungen (Stufe schon vorhanden): {$skippedDuplicate}\n";
echo "Übersprungen (keine Kunden-E-Mail): {$skippedNoEmail}\n";
echo "Fehler:                            {$errorCount}\n";
echo "\nFertig: " . date('Y-m-d H:i:s') . "\n";

exit($errorCount > 0 ? 4 : 0);
