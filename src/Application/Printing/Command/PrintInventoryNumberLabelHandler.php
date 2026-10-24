<?php

declare(strict_types=1);

namespace App\Application\Printing\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Printing\LabelPrinterRepositoryInterface;
use App\Infrastructure\Printing\LabelContent;
use App\Infrastructure\Printing\LabelPrinterDriverRegistry;
use App\Infrastructure\Printing\PrinterConnectionException;
use App\Infrastructure\Printing\PrinterProfile;
use App\Infrastructure\Printing\TcpPrinterTransport;

/**
 * Druckt ein Etikett (Barcode + Klartext) für eine einzelne Inventarnummer
 * auf einem konfigurierten Etikettendrucker. Orchestriert nur: lädt
 * Inventarnummer + Druckerprofil, baut den generischen LabelContent, lässt
 * den passenden Treiber (siehe LabelPrinterDriverRegistry) daraus die
 * druckfertigen Bytes erzeugen und übergibt diese an den TCP-Transport.
 * Die eigentliche Socket-I/O und das Druckerprotokoll bleiben vollständig
 * in der Infrastructure-Schicht.
 *
 * @throws PrinterConnectionException bei Verbindungs-/Übertragungsfehlern.
 */
final class PrintInventoryNumberLabelHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly LabelPrinterRepositoryInterface $printers,
        private readonly LabelPrinterDriverRegistry $drivers,
        private readonly TcpPrinterTransport $transport,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof PrintInventoryNumberLabel);

        $inventoryNumber = $this->inventoryNumbers->findById($command->inventoryNumberId);
        if ($inventoryNumber === null) {
            throw new NotFoundException("Inventarnummer \"{$command->inventoryNumberId}\" wurde nicht gefunden.");
        }

        $printer = $this->printers->findById($command->printerId);
        if ($printer === null) {
            throw new NotFoundException("Etikettendrucker \"{$command->printerId}\" wurde nicht gefunden.");
        }

        $content = new LabelContent(
            barcode: ['type' => 'code128', 'value' => $inventoryNumber->number],
            lines: [$inventoryNumber->number],
        );

        $driver = $this->drivers->get($printer->driverType);
        $profile = PrinterProfile::fromLabelPrinter($printer);

        $bytes = $driver->renderLabel($content, $profile);
        $this->transport->send($printer->ipAddress, $printer->port, $bytes);

        return null;
    }
}
