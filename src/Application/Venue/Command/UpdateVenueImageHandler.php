<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Venue\VenueImageRepositoryInterface;

final class UpdateVenueImageHandler implements CommandHandlerInterface
{
    use NormalizesVenueImageDescription;

    public function __construct(private readonly VenueImageRepositoryInterface $images)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateVenueImage);

        $image = $this->images->findById($command->id);
        if ($image === null || $image->venueId !== $command->venueId) {
            throw new NotFoundException('Bild wurde nicht gefunden.');
        }

        $image->description = $this->normalizeDescription($command->description);
        $this->images->save($image);

        return null;
    }
}
