#!/usr/bin/env php
<?php

declare(strict_types=1);

// Cron-Skript (empfohlen: stündlich) für Erinnerungen an Techniker, die eine
// Anfrage noch nicht beantwortet haben (Migration 134). Die Zeitpunkte
// stehen unter Einstellungen › Techniker & Gewerke (z. B. "24, 72" = nach
// 24 und nach 72 Stunden). Je Anfrage wird jede Erinnerung höchstens einmal
// verschickt (reminder_count); Anfragen zu bereits vergangenen
// Veranstaltungen werden übersprungen. Muss — wie die übrigen bin/-Skripte —
// per Cronjob eingerichtet werden, z. B.:
//   15 * * * * php /pfad/zur/app/bin/send-technician-reminders.php

$router = require __DIR__ . '/../src/bootstrap.php';

foreach (['rentalTechnicianRepository', 'technicianSettingsRepository', 'technicianNotifier', 'rentalRepository'] as $variable) {
    if (!isset($$variable)) {
        fwrite(STDERR, "Fehler: \${$variable} wurde nicht aus bootstrap.php übernommen.\n");
        exit(1);
    }
}

echo "Materialverleih — Erinnerungen an offene Techniker-Anfragen\n";
echo 'Gestartet: ' . date('Y-m-d H:i:s') . "\n\n";

$settings = $technicianSettingsRepository->get();
$now = new DateTimeImmutable();
$today = $now->format('Y-m-d');
$sent = 0;
$failed = 0;

foreach ($rentalTechnicianRepository->findOpenRequests() as $request) {
    $dueAfter = $settings->reminderDueAfterHours($request->reminderCount);
    if ($dueAfter === null) {
        continue; // alle Erinnerungen verschickt
    }
    $hoursSince = ($now->getTimestamp() - $request->requestedAt->getTimestamp()) / 3600;
    if ($hoursSince < $dueAfter) {
        continue;
    }
    $rental = $rentalRepository->findById($request->rentalId);
    if ($rental === null || $rental->endDate->format('Y-m-d') < $today) {
        continue;
    }
    try {
        $result = $technicianNotifier->notify($request->id, true);
        $sent++;
        echo sprintf("✓ %s – Erinnerung %d%s\n", $rental->eventName, $request->reminderCount + 1, $result['errors'] !== [] ? ' (Fehler: ' . implode('; ', $result['errors']) . ')' : '');
    } catch (Throwable $e) {
        $failed++;
        echo sprintf("✗ %s – %s\n", $rental->eventName, $e->getMessage());
    }
}

echo "\nVerschickt: {$sent}, fehlgeschlagen: {$failed}\n";
exit($failed > 0 ? 1 : 0);
