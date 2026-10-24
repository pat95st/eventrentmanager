<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Zentrale, laufzeit-ermittelte Pfade des Projekts — bewusst NIE fest
 * kodiert, damit sie auf jedem Hosting-Server automatisch stimmen, egal
 * wohin das Projekt entpackt/deployed wurde (siehe bin/import-receipt-
 * emails.php sowie App\Infrastructure\Http\Controllers\
 * MailboxSettingsController, die beide dieselbe Methode nutzen, damit der
 * im Admin-UI angezeigte Cronjob-Pfad garantiert exakt dem Pfad
 * entspricht, unter dem das Skript tatsächlich ausgeführt wird).
 */
final class ProjectPaths
{
    /** Absolutes Projekt-Wurzelverzeichnis (enthält u. a. src/, bin/, public/). */
    public static function rootDirectory(): string
    {
        // Diese Datei liegt unter src/Shared/ProjectPaths.php, die Wurzel
        // ist also zwei Verzeichnisebenen darüber.
        $root = realpath(__DIR__ . '/../..');

        return $root !== false ? $root : __DIR__ . '/../..';
    }

    /** Absoluter Pfad zum Cron-Einsammel-Skript für den Beleg-Posteingang. */
    public static function importReceiptEmailsScriptPath(): string
    {
        $path = realpath(self::rootDirectory() . '/bin/import-receipt-emails.php');

        return $path !== false ? $path : self::rootDirectory() . '/bin/import-receipt-emails.php';
    }
}
