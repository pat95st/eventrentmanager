#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Application\ReceiptInbox\Command\ImportReceiptEmails;
use App\Infrastructure\Mail\MailboxClientException;

// Cron-Einsammel-Skript für den Beleg-Posteingang: ruft (sofern in den
// Mailserver-Einstellungen aktiviert) alle Nachrichten aus dem
// konfigurierten Postfach ab und speichert neue, auf der Allow-Liste
// stehende Nachrichten mit PDF/JPG/PNG-Anhängen (siehe
// App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler). Läuft
// NICHT automatisch im Web-Request — muss per Cronjob eingerichtet werden
// (siehe /einstellungen/mailserver im Admin-Bereich für den auf DIESEM
// Server tatsächlich gültigen Pfad und eine Beispiel-Cronjob-Zeile).
//
// WICHTIG — Pfad-Unabhängigkeit vom Hosting-Server: dieses Skript enthält
// bewusst KEINEN absoluten, fest kodierten Pfad zu sich selbst oder zum
// Projekt. Alles wird relativ über __DIR__ aufgelöst, damit es unverändert
// funktioniert, egal wohin das Projekt entpackt/deployed wurde.
//
// Bootstrap-Zugriff — gewählter Ansatz und Begründung: src/bootstrap.php
// baut (siehe dortige Kommentare) AUSSCHLIESSLICH den Router per
// Dependency-Wiring auf und gibt ihn am Dateiende per `return $router;`
// zurück — es wird DORT selbst nirgends `$router->dispatch(...)`
// aufgerufen (das passiert separat, ausschließlich in public/index.php).
// Ein Top-Level-`require` (kein Aufruf innerhalb einer Funktion/Methode)
// teilt sich denselben Dateigeltungsbereich wie die eingebundene Datei,
// PHP importiert dadurch alle in bootstrap.php definierten lokalen
// Variablen (u. a. $commandBus) automatisch in dieses Skript — das wurde
// unten per var_dump-freiem Zugriff auf $commandBus EXPLIZIT getestet
// (siehe Verifikationsbericht) und funktioniert zuverlässig, SOLANGE dieses
// `require` ebenfalls auf oberster Ebene steht (nicht in einer Funktion).
// Genau das ist hier der Fall. Das erspart eine zweite, redundante
// Kopie der DB-Verbindungs-/Repository-Verdrahtung, die bei künftigen
// Refactorings von bootstrap.php sonst leicht auseinanderlaufen könnte.
/** @var \App\Infrastructure\Http\Router $router Ungenutzt — wir brauchen nur die lokalen Variablen, die bootstrap.php nebenbei definiert. */
$router = require __DIR__ . '/../src/bootstrap.php';

if (!isset($commandBus)) {
    fwrite(STDERR, "Fehler: \$commandBus wurde nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

echo "Materialverleih — Beleg-Posteingang-Import\n";
echo str_repeat('-', 43) . "\n";
echo 'Gestartet: ' . date('Y-m-d H:i:s') . "\n\n";

try {
    /** @var \App\Application\ReceiptInbox\Command\ImportReceiptEmailsResult $result */
    $result = $commandBus->dispatch(new ImportReceiptEmails());
} catch (MailboxClientException $e) {
    fwrite(STDERR, 'Verbindungsfehler beim Abruf des Postfachs: ' . $e->getMessage() . "\n");
    exit(2);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Unerwarteter Fehler beim Import: ' . $e->getMessage() . "\n");
    exit(3);
}

if ($result->disabled) {
    echo "Import deaktiviert (siehe Einstellungen -> Mailserver -> \"Import aktivieren\").\n";
    exit(0);
}

echo "Neue Nachrichten importiert:    {$result->importedMessages}\n";
echo "Neue Anhänge gespeichert:       {$result->importedAttachments}\n";
echo "Ignoriert (nicht auf Allow-Liste): {$result->ignoredNotAllowed}\n";
echo "Ignoriert (kein gültiger Anhang):  {$result->ignoredNoAttachment}\n";
echo "Übersprungen (bereits bekannt):    {$result->skippedDuplicates}\n";
echo "\nFertig: " . date('Y-m-d H:i:s') . "\n";

exit(0);
