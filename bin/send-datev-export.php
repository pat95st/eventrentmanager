#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Application\Datev\Command\CreateDatevExport;
use App\Application\Datev\DatevExportPackage;
use App\Domain\Datev\DatevExportTrigger;

// Cron-Skript für den automatischen DATEV-Export an den Steuerberater
// (Einstellungen → Integrationen → DATEV → "Export automatisch per E-Mail
// senden"). Läuft NICHT im Web-Request (siehe bin/send-dunning-notices.php
// für das Bootstrap-Zugriffsmuster) und muss per Cronjob eingerichtet werden
// — empfohlen einmal täglich, z. B.:
//
//   15 6 * * * php /pfad/zur/anwendung/bin/send-datev-export.php
//
// Ablauf:
//   1. Abbruch, wenn DATEV-Export oder automatischer Versand deaktiviert ist.
//   2. Ermittelt den letzten abgeschlossenen Zeitraum (Vormonat bzw.
//      Vorquartal, siehe DatevExportInterval::previousPeriod()).
//   3. Versand erst ab dem eingestellten Tag des Folgemonats
//      (DatevSettings::$sendDay), damit spät eintreffende Belege noch
//      erfasst werden können.
//   4. Wurde dieser Zeitraum bereits automatisch versendet (Protokoll
//      datev_exports), passiert nichts — jeder Zeitraum geht genau einmal
//      raus, auch bei mehrfachen Läufen. Schlägt der Versand fehl, wird
//      nichts protokolliert und der nächste Lauf versucht es erneut.
//
// Optional: --force verschickt den Zeitraum trotz bereits erfolgtem Versand
// bzw. vor dem Versandtag erneut.
/** @var \App\Infrastructure\Http\Router $router Ungenutzt — wir brauchen nur die lokalen Variablen, die bootstrap.php nebenbei definiert. */
$router = require __DIR__ . '/../src/bootstrap.php';

if (!isset($commandBus) || !isset($datevSettingsRepository) || !isset($datevExportRepository)) {
    fwrite(STDERR, "Fehler: \$commandBus/\$datevSettingsRepository/\$datevExportRepository wurden nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

$force = in_array('--force', $argv, true);

echo "Materialverleih — Automatischer DATEV-Export\n";
echo str_repeat('-', 44) . "\n";
echo 'Gestartet: ' . date('Y-m-d H:i:s') . "\n\n";

/** @var \App\Domain\Datev\DatevSettings $settings */
$settings = $datevSettingsRepository->get();

if (!$settings->enabled || !$settings->autoSendEnabled) {
    echo "Automatischer DATEV-Versand deaktiviert (siehe Einstellungen -> Integrationen -> DATEV).\n";
    exit(0);
}

$today = new DateTimeImmutable('today');
[$from, $to] = $settings->interval->previousPeriod($today);

echo 'Zeitraum: ' . $from->format('d.m.Y') . ' - ' . $to->format('d.m.Y') . ' (' . $settings->interval->label() . ")\n";

if (!$force && (int) $today->format('j') < $settings->sendDay) {
    echo "Versand erst ab dem {$settings->sendDay}. des Monats — nichts zu tun.\n";
    exit(0);
}

if (!$force && $datevExportRepository->existsAutoSentForPeriod($from, $to)) {
    echo "Dieser Zeitraum wurde bereits automatisch versendet — nichts zu tun.\n";
    exit(0);
}

try {
    $package = $commandBus->dispatch(new CreateDatevExport($from, $to, DatevExportTrigger::AutoEmail));
    assert($package instanceof DatevExportPackage);
} catch (\App\Domain\Exception\ValidationException $e) {
    fwrite(STDERR, 'Fehler: ' . implode(' ', $e->errors()) . "\n");
    exit(4);
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler beim DATEV-Export: ' . $e->getMessage() . "\n");
    exit(4);
}

echo "An {$settings->advisorEmail} versendet: {$package->filename}\n";
echo "Ausgangsrechnungen:   {$package->invoiceCount}\n";
echo "Korrekturen/Stornos:  {$package->correctionCount}\n";
echo "Eingangsbelege:       {$package->receiptCount}\n";
echo "Belegdateien:         {$package->documentCount}\n";
foreach ($package->warnings as $warning) {
    echo "Hinweis: {$warning}\n";
}
echo "\nFertig: " . date('Y-m-d H:i:s') . "\n";

exit(0);
