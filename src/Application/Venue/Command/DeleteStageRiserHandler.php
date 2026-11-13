<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Venue\VenueStageRepositoryInterface;
use App\Domain\Venue\VenueStageRiserRepositoryInterface;

final class DeleteStageRiserHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly VenueStageRiserRepositoryInterface $risers,
        private readonly VenueStageRepositoryInterface $stages,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteStageRiser);

        // Bewusst ohne die Maß-Prüfung aus HandlesStageRisers: Riser müssen sich
        // auch dann noch löschen lassen, wenn die Bühne inzwischen keine festen Maße mehr hat.
        $stage = $this->stages->findById($command->stageId);
        $riser = $this->risers->findById($command->id);
        if ($stage === null || $stage->venueId !== $command->venueId || $riser === null || $riser->stageId !== $stage->id) {
            throw new NotFoundException("Riser \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->risers->delete($riser->id);

        return null;
    }
}
