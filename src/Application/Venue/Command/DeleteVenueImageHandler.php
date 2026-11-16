<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Venue\VenueImageRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteVenueImageHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly VenueImageRepositoryInterface $images,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteVenueImage);

        $image = $this->images->findById($command->id);
        if ($image === null || $image->venueId !== $command->venueId) {
            throw new NotFoundException('Bild wurde nicht gefunden.');
        }

        $this->images->delete($image->id);
        try {
            $this->fileStorage->delete(AddVenueImageHandler::STORAGE_DIRECTORY, $image->filePath);
        } catch (\Throwable) {
            // File already gone — the record is removed, that is enough.
        }

        return null;
    }
}
