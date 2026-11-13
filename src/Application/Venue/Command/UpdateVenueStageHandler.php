<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\VenuePowerConnectionRepositoryInterface;
use App\Domain\Venue\VenueStageRepositoryInterface;

final class UpdateVenueStageHandler implements CommandHandlerInterface
{
    use ValidatesVenueStageInput;

    public function __construct(
        private readonly VenueStageRepositoryInterface $stages,
        private readonly VenuePowerConnectionRepositoryInterface $connections,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateVenueStage);

        $stage = $this->stages->findById($command->id);
        if ($stage === null || $stage->venueId !== $command->venueId) {
            throw new NotFoundException("Bühne \"{$command->id}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateVenueStageInput(
            $command->name,
            $command->width,
            $command->depth,
            $command->height,
            $command->type,
            $command->powerDistances,
            $this->connections->findByVenue($stage->venueId),
            $command->buildMode,
            $command->elementStock,
        );

        if ($data === null) {
            throw new ValidationException($errors);
        }

        $stage->name = $data['name'];
        $stage->widthM = $data['width'];
        $stage->depthM = $data['depth'];
        $stage->heightM = $data['height'];
        $stage->type = $data['type'];
        $stage->powerDistances = $data['powerDistances'];
        $stage->elementStock = $data['elementStock'];

        $this->stages->save($stage);

        return null;
    }
}
