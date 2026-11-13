<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\VenueStageRepositoryInterface;
use App\Domain\Venue\VenueStageRiserRepositoryInterface;
use App\Domain\Venue\Riser;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AddStageRiserHandler implements CommandHandlerInterface
{
    use HandlesStageRisers;

    public function __construct(
        private readonly VenueStageRiserRepositoryInterface $risers,
        private readonly VenueStageRepositoryInterface $stages,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddStageRiser);

        $stage = $this->stageForRisers($command->stageId, $command->venueId);

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

        $riser = new Riser(
            id: Uuid::v4(),
            stageId: $stage->id,
            name: $data['name'],
            shape: $data['shape'],
            widthM: $data['width'],
            depthM: $data['depth'],
            heightM: $data['height'],
            centerXM: $data['centerX'],
            centerYM: $data['centerY'],
            rotationDeg: $data['rotation'],
            createdAt: new DateTimeImmutable(),
        );

        $this->risers->save($riser);

        return $riser->id;
    }
}
