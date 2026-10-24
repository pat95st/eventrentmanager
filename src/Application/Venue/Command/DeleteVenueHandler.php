<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Venue\VenueRepositoryInterface;

final class DeleteVenueHandler implements CommandHandlerInterface
{
    public function __construct(private readonly VenueRepositoryInterface $venues)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteVenue);

        $venue = $this->venues->findById($command->id);
        if ($venue === null) {
            throw new NotFoundException("Veranstaltungsort \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->venues->isUsedByRentals($command->id)) {
            throw new ConflictException('Dieser Veranstaltungsort ist noch mindestens einer Vermietung zugeordnet. Bitte zuerst die Zuordnung entfernen.');
        }

        $this->venues->delete($command->id);

        return null;
    }
}
