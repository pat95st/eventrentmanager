<?php

declare(strict_types=1);

use App\Infrastructure\Config\NotInstalledException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

// Das Laden von bootstrap.php selbst (nicht erst das spätere Routing) kann
// fehlschlagen, wenn noch keine database.yaml existiert — das ist der
// Normalfall bei einer frischen, noch nicht installierten Kopie der
// Anwendung. Dieser eine Fall bekommt bewusst eine eigene, hilfreiche
// Weiterleitung zum Web-Installer statt der generischen Fehlerseite unten.
try {
    /** @var \App\Infrastructure\Http\Router $router */
    $router = require __DIR__ . '/../src/bootstrap.php';
} catch (NotInstalledException $e) {
    header('Location: /install.php');
    exit;
} catch (\Throwable $e) {
    error_log(sprintf(
        '[Materialverleih] Fehler beim Laden der Anwendung: %s in %s:%d%s%s',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL,
        $e->getTraceAsString()
    ));

    Response::serverError();
    exit;
}

// Jeder nicht anderweitig abgefangene Fehler (z. B. eine PDOException durch
// eine fehlende Tabelle, weil eine Migration noch nicht eingespielt wurde)
// landete bisher als leere weiße Seite beim Benutzer, da PHP bei
// display_errors=Off einfach nichts ausgibt. Statt dessen: Fehler ins
// Server-Error-Log schreiben (für die Fehlersuche) und eine freundliche
// Fehlerseite anzeigen (siehe Response::serverError()/errors/500.php).
try {
    $router->dispatch(Request::fromGlobals());
} catch (\Throwable $e) {
    error_log(sprintf(
        '[Materialverleih] Unbehandelter Fehler: %s in %s:%d%s%s',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL,
        $e->getTraceAsString()
    ));

    Response::serverError();
}
