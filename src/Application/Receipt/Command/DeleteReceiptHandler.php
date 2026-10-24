<?php

declare(strict_types=1);

namespace App\Application\Receipt\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteReceiptHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly LocalFileStorage $fileStorage,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteReceipt);

        $receipt = $this->receipts->findById($command->id);
        if ($receipt === null) {
            throw new NotFoundException("Beleg \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->inventoryNumbers->findByReceipt($receipt->id) !== []) {
            throw new ConflictException('Diesem Beleg sind noch Inventarnummern zugeordnet. Bitte zuerst die Verknüpfung entfernen.');
        }

        $this->receipts->delete($receipt->id);
        $this->fileStorage->delete('receipts', $receipt->storedFilename);

        return null;
    }
}
