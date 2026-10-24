<?php

declare(strict_types=1);

namespace App\Application\Printing\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Printing\LabelPrinterRepositoryInterface;
use App\Infrastructure\Printing\LabelPrinterDriverRegistry;
use App\Infrastructure\Printing\PrinterConnectionException;
use App\Infrastructure\Printing\PrinterProfile;

/**
 * Orchestriert den "Testdruck"-Button der Drucker-Einstellungen: lädt das
 * gespeicherte Druckerprofil, löst über die Registry den passenden Treiber
 * auf und lässt ihn ein kleines Testetikett erzeugen/versenden. Die
 * eigentliche Socket-I/O steckt im Treiber/Transport (Infrastructure),
 * hier passiert nur die fachliche Verdrahtung — analog zur Trennung bei
 * EmailPackingListPdfHandler (Orchestrierung) vs. SimplePdfWriter (Low-Level).
 *
 * @throws PrinterConnectionException bei Verbindungs-/Übertragungsfehlern —
 *         wird vom Controller abgefangen und als deutsche Fehlermeldung angezeigt.
 */
final class SendTestPrintHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly LabelPrinterRepositoryInterface $printers,
        private readonly LabelPrinterDriverRegistry $drivers,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SendTestPrint);

        $printer = $this->printers->findById($command->printerId);
        if ($printer === null) {
            throw new NotFoundException("Etikettendrucker \"{$command->printerId}\" wurde nicht gefunden.");
        }

        $driver = $this->drivers->get($printer->driverType);
        $profile = PrinterProfile::fromLabelPrinter($printer);

        $driver->sendTestPrint($profile);

        return null;
    }
}
