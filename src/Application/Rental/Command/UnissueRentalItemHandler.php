<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;

final class UnissueRentalItemHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RentalIssuedItemRepositoryInterface $issuedItems)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UnissueRentalItem);

        $item = $this->issuedItems->findById($command->id);
        if ($item === null) {
            throw new NotFoundException("Ausgabe-Eintrag \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->issuedItems->delete($command->id);

        return null;
    }
}
