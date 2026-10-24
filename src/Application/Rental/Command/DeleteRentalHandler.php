<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalRepositoryInterface;

final class DeleteRentalHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RentalRepositoryInterface $rentals)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteRental);

        $rental = $this->rentals->findById($command->id);
        if ($rental === null) {
            throw new NotFoundException("Vermietung \"{$command->id}\" wurde nicht gefunden.");
        }

        // Geplante Posten (rental_items) und Ausgabe-Einträge (rental_issued_items)
        // hängen per FK ON DELETE CASCADE an rentals und werden mit entfernt.
        $this->rentals->delete($command->id);

        return null;
    }
}
