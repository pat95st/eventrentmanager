<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalItemRepositoryInterface;
use DateTimeImmutable;

final class ConfirmRentalItemPreIssueChecksHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RentalItemRepositoryInterface $items)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ConfirmRentalItemPreIssueChecks);

        $item = $this->items->findById($command->rentalItemId);
        if ($item === null) {
            throw new NotFoundException("Posten \"{$command->rentalItemId}\" wurde nicht gefunden.");
        }

        $item->preIssueChecksConfirmed = true;
        $item->updatedAt = new DateTimeImmutable();

        $this->items->save($item);

        return null;
    }
}
