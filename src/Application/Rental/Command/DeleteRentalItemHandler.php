<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;

final class DeleteRentalItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalItemRepositoryInterface $items,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteRentalItem);

        $item = $this->items->findById($command->id);
        if ($item === null) {
            throw new NotFoundException("Posten \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->issuedItems->countByRentalAndArticle($item->rentalId, $item->articleId) > 0) {
            throw new ConflictException('Für diesen Artikel wurden bereits Inventarnummern ausgegeben. Bitte zuerst die Ausgabe rückgängig machen.');
        }

        $this->items->delete($command->id);

        return null;
    }
}
