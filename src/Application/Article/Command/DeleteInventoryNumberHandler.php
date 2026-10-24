<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rma\RmaRepositoryInterface;
use App\Domain\Sale\SaleRepositoryInterface;

final class DeleteInventoryNumberHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly RmaRepositoryInterface $rmas,
        private readonly SaleRepositoryInterface $sales,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteInventoryNumber);

        $inventoryNumber = $this->inventoryNumbers->findById($command->id);
        if ($inventoryNumber === null) {
            throw new NotFoundException("Inventurnummer \"{$command->id}\" wurde nicht gefunden.");
        }

        // Verkaufte Exemplare dürfen NIE gelöscht werden — sonst würde ihre
        // Nummer vom InventoryNumberGenerator als wieder frei erkannt und
        // künftig neu vergeben (siehe Sale-Klassen-Doc).
        if ($this->sales->findByInventoryNumber($command->id) !== null) {
            throw new ConflictException(
                'Diese Inventarnummer wurde bereits verkauft und kann daher nicht mehr gelöscht werden.'
            );
        }

        if ($this->rmas->countByInventoryNumber($command->id) > 0) {
            throw new ConflictException(
                'Zu dieser Inventurnummer sind noch RMA-Vorgänge hinterlegt. Bitte zuerst löschen, damit keine Dokumente verwaist zurückbleiben.'
            );
        }

        $this->inventoryNumbers->delete($command->id);

        return null;
    }
}
