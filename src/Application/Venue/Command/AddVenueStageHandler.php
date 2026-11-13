<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\Stage;
use App\Domain\Venue\VenuePowerConnectionRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Domain\Venue\VenueStageRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AddVenueStageHandler implements CommandHandlerInterface
{
    use ValidatesVenueStageInput;

    public function __construct(
        private readonly VenueStageRepositoryInterface $stages,
        private readonly VenueRepositoryInterface $venues,
        private readonly VenuePowerConnectionRepositoryInterface $connections,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddVenueStage);

        if ($this->venues->findById($command->venueId) === null) {
            throw new NotFoundException("Veranstaltungsort \"{$command->venueId}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateVenueStageInput(
            $command->name,
            $command->width,
            $command->depth,
            $command->height,
            $command->type,
            $command->powerDistances,
            $this->connections->findByVenue($command->venueId),
        );

        if ($data === null) {
            throw new ValidationException($errors);
        }

        $stage = new Stage(
            id: Uuid::v4(),
            venueId: $command->venueId,
            name: $data['name'],
            widthM: $data['width'],
            depthM: $data['depth'],
            heightM: $data['height'],
            type: $data['type'],
            powerDistances: $data['powerDistances'],
            createdAt: new DateTimeImmutable(),
        );

        $this->stages->save($stage);

        return $stage->id;
    }
}
