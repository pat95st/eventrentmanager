<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\VenuePowerConnectionRepositoryInterface;

final class UpdateVenuePowerConnectionHandler implements CommandHandlerInterface
{
    use ValidatesVenuePowerConnectionInput;

    public function __construct(private readonly VenuePowerConnectionRepositoryInterface $connections)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateVenuePowerConnection);

        $connection = $this->connections->findById($command->id);
        if ($connection === null || $connection->venueId !== $command->venueId) {
            throw new NotFoundException("Stromanschluss \"{$command->id}\" wurde nicht gefunden.");
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

        $connection->type = $data['type'];
        $connection->breakerRating = $data['breakerRating'];
        $connection->rcdTripCurrent = $data['rcdTripCurrent'];
        $connection->location = $data['location'];

        $this->connections->save($connection);

        return null;
    }
}
