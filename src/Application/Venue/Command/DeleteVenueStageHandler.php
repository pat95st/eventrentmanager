<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Venue\VenueStageRepositoryInterface;

final class DeleteVenueStageHandler implements CommandHandlerInterface
{
    public function __construct(private readonly VenueStageRepositoryInterface $stages)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteVenueStage);

        $stage = $this->stages->findById($command->id);
        if ($stage === null || $stage->venueId !== $command->venueId) {
            throw new NotFoundException("Bühne \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->stages->delete($command->id);

        return null;
    }
}
