<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\VenueStageRepositoryInterface;
use App\Domain\Venue\VenueStageRiserRepositoryInterface;

final class UpdateStageRiserHandler implements CommandHandlerInterface
{
    use HandlesStageRisers;

    public function __construct(
        private readonly VenueStageRiserRepositoryInterface $risers,
        private readonly VenueStageRepositoryInterface $stages,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateStageRiser);

        $stage = $this->stageForRisers($command->stageId, $command->venueId);
        $riser = $this->riserOnStage($command->id, $stage);

        [$errors, $data] = $this->validateRiserInput(
            $stage,
            $command->name,
            $command->shape,
            $command->width,
            $command->depth,
            $command->height,
            $command->centerX,
            $command->centerY,
            $command->rotation,
        );
        if ($data === null) {
            throw new ValidationException($errors);
        }

        $riser->name = $data['name'];
        $riser->shape = $data['shape'];
        $riser->widthM = $data['width'];
        $riser->depthM = $data['depth'];
        $riser->heightM = $data['height'];
        $riser->centerXM = $data['centerX'];
        $riser->centerYM = $data['centerY'];
        $riser->rotationDeg = $data['rotation'];

        $this->risers->save($riser);

        return null;
    }
}
