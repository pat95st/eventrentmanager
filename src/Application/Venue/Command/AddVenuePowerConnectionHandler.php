<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\PowerConnection;
use App\Domain\Venue\VenuePowerConnectionRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AddVenuePowerConnectionHandler implements CommandHandlerInterface
{
    use ValidatesVenuePowerConnectionInput;

    public function __construct(
        private readonly VenuePowerConnectionRepositoryInterface $connections,
        private readonly VenueRepositoryInterface $venues,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddVenuePowerConnection);

        if ($this->venues->findById($command->venueId) === null) {
            throw new NotFoundException("Veranstaltungsort \"{$command->venueId}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateVenuePowerConnectionInput(
            $command->type,
            $command->breakerRating,
            $command->rcdTripCurrent,
            $command->location,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $connection = new PowerConnection(
            id: Uuid::v4(),
            venueId: $command->venueId,
            type: $data['type'],
            breakerRating: $data['breakerRating'],
            rcdTripCurrent: $data['rcdTripCurrent'],
            location: $data['location'],
            createdAt: new DateTimeImmutable(),
        );

        $this->connections->save($connection);

        return $connection->id;
    }
}
