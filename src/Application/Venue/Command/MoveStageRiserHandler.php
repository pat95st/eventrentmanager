<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\VenueStageRepositoryInterface;
use App\Domain\Venue\VenueStageRiserRepositoryInterface;

final class MoveStageRiserHandler implements CommandHandlerInterface
{
    use HandlesStageRisers;

    public function __construct(
        private readonly VenueStageRiserRepositoryInterface $risers,
        private readonly VenueStageRepositoryInterface $stages,
    ) {
    }

    /** @return array{x: float, y: float} die gespeicherte Position */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof MoveStageRiser);

        $stage = $this->stageForRisers($command->stageId, $command->venueId);
        $riser = $this->riserOnStage($command->id, $stage);

        // Beim Verschieben ist die Position Pflicht (leer hieße sonst "Bühnenmitte").
        if (trim($command->centerX) === '' || trim($command->centerY) === '') {
            throw new ValidationException(['centerX' => 'Die neue Position des Risers fehlt.']);
        }

        [$errors, $position] = $this->validateRiserPosition($stage, $command->centerX, $command->centerY);
        if ($position === null) {
            throw new ValidationException($errors);
        }

        $riser->centerXM = $position['x'];
        $riser->centerYM = $position['y'];
        $this->risers->save($riser);

        return $position;
    }
}
