<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Venue\VenuePowerConnectionRepositoryInterface;

final class DeleteVenuePowerConnectionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly VenuePowerConnectionRepositoryInterface $connections)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteVenuePowerConnection);

        $connection = $this->connections->findById($command->id);
        if ($connection === null || $connection->venueId !== $command->venueId) {
            throw new NotFoundException("Stromanschluss \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->connections->delete($command->id);

        return null;
    }
}
