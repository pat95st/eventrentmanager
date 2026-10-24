<?php

declare(strict_types=1);

namespace App\Infrastructure\Printing;

use RuntimeException;

/**
 * Wird bei jedem Fehlschlag der TCP-Verbindung zu einem Etikettendrucker
 * geworfen (Verbindung abgelehnt, Zeitüberschreitung, Host nicht erreichbar,
 * Schreibfehler) — bewusst eine einzige, fachliche Exception-Klasse mit
 * genauer Meldung im Text, statt roher Fehler aus sockets/streams, damit der
 * Controller sie gezielt abfangen und dem Benutzer eine verständliche
 * deutsche Meldung anzeigen kann (siehe LabelPrinterController::testPrint()).
 */
final class PrinterConnectionException extends RuntimeException
{
}
