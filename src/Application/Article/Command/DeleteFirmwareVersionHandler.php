<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\FirmwareVersionRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;

final class DeleteFirmwareVersionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly FirmwareVersionRepositoryInterface $firmwareVersions,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteFirmwareVersion);

        $firmwareVersion = $this->firmwareVersions->findById($command->id);
        if ($firmwareVersion === null) {
            throw new NotFoundException("Firmware-Version \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->inventoryNumbers->existsByInstalledFirmwareVersion($firmwareVersion->id)) {
            throw new ConflictException('Diese Firmware-Version ist noch auf mindestens einer Inventurnummer als installiert hinterlegt. Bitte zuerst dort eine andere Version auswählen.');
        }

        $this->firmwareVersions->delete($firmwareVersion->id);

        return null;
    }
}
