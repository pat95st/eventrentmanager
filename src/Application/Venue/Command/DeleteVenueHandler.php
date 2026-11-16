<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Venue\VenueImageRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteVenueHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly VenueRepositoryInterface $venues,
        private readonly ?VenueImageRepositoryInterface $images = null,
        private readonly ?LocalFileStorage $fileStorage = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteVenue);

        $venue = $this->venues->findById($command->id);
        if ($venue === null) {
            throw new NotFoundException("Veranstaltungsort \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->venues->isUsedByRentals($command->id)) {
            throw new ConflictException('Dieser Veranstaltungsort ist noch mindestens einer Vermietung zugeordnet. Bitte zuerst die Zuordnung entfernen.');
        }

        // Image rows are removed via ON DELETE CASCADE; remember the files first.
        $images = $this->images !== null ? $this->images->findByVenue($command->id) : [];

        $this->venues->delete($command->id);

        if ($this->fileStorage !== null) {
            foreach ($images as $image) {
                try {
                    $this->fileStorage->delete(AddVenueImageHandler::STORAGE_DIRECTORY, $image->filePath);
                } catch (\Throwable) {
                    // Orphaned file is harmless; the venue is deleted.
                }
            }
        }

        return null;
    }
}
