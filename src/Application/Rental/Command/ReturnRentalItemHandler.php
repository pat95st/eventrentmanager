<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Rma\Rma;
use App\Domain\Rma\RmaRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Markiert einen Ausgabe-Eintrag als zurückgegeben. War der Funktionstest
 * nicht erfolgreich, wird automatisch ein RMA-Vorgang für die betroffene
 * Inventarnummer angelegt (direkt über das Repository, nicht über den
 * CommandBus — siehe Handler-Konvention in diesem Projekt: Handler rufen
 * Repositories auf, nie andere Handler).
 */
final class ReturnRentalItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly RentalRepositoryInterface $rentals,
        private readonly RmaRepositoryInterface $rmas,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ReturnRentalItem);

        $issuedItem = $this->issuedItems->findById($command->issuedItemId);
        if ($issuedItem === null) {
            throw new NotFoundException("Ausgabe-Eintrag \"{$command->issuedItemId}\" wurde nicht gefunden.");
        }

        $now = new DateTimeImmutable();
        $issuedItem->returnedAt = $now;
        $issuedItem->functionTestPassed = $command->functionTestPassed;

        $this->issuedItems->save($issuedItem);

        if (!$command->functionTestPassed) {
            $rental = $this->rentals->findById($issuedItem->rentalId);
            $eventName = $rental?->eventName ?? 'unbekannte Vermietung';

            $rma = new Rma(
                id: Uuid::v4(),
                inventoryNumberId: $issuedItem->inventoryNumberId,
                reason: "Funktionstest bei Rückgabe aus Vermietung \"{$eventName}\" nicht erfolgreich.",
                serviceProvider: null,
                rmaReference: null,
                sentAt: null,
                returnedAt: null,
                notes: null,
                createdAt: $now,
                updatedAt: $now,
            );

            $this->rmas->save($rma);
        }

        return null;
    }
}
