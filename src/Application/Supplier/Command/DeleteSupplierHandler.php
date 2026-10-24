<?php

declare(strict_types=1);

namespace App\Application\Supplier\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Supplier\SupplierRepositoryInterface;

final class DeleteSupplierHandler implements CommandHandlerInterface
{
    public function __construct(private readonly SupplierRepositoryInterface $suppliers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteSupplier);

        $supplier = $this->suppliers->findById($command->id);
        if ($supplier === null) {
            throw new NotFoundException("Lieferant \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->suppliers->isUsedByInventoryNumbers($command->id)) {
            throw new ConflictException('Dieser Lieferant ist noch mindestens einer Inventurnummer zugeordnet. Bitte zuerst die Zuordnung entfernen.');
        }

        if ($this->suppliers->isUsedByReceipts($command->id)) {
            throw new ConflictException('Dieser Lieferant ist noch mindestens einem Beleg zugeordnet. Bitte zuerst die Zuordnung entfernen.');
        }

        $this->suppliers->delete($command->id);

        return null;
    }
}
