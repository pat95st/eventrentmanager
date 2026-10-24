<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;

/**
 * Macht eine Rückgabe rückgängig (z. B. Fehlbedienung). Ein dabei automatisch
 * angelegter RMA-Vorgang wird bewusst NICHT gelöscht, da er unabhängig von der
 * Rückgabe-Erfassung Bestand haben soll (siehe Dokumentation).
 */
final class UndoReturnRentalItemHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RentalIssuedItemRepositoryInterface $issuedItems)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UndoReturnRentalItem);

        $issuedItem = $this->issuedItems->findById($command->issuedItemId);
        if ($issuedItem === null) {
            throw new NotFoundException("Ausgabe-Eintrag \"{$command->issuedItemId}\" wurde nicht gefunden.");
        }

        $issuedItem->returnedAt = null;
        $issuedItem->functionTestPassed = null;

        $this->issuedItems->save($issuedItem);

        return null;
    }
}
