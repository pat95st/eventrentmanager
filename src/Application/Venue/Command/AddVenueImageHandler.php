<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\VenueImage;
use App\Domain\Venue\VenueImageRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

/** See AddArticleImageHandler (same formats and size limit). */
final class AddVenueImageHandler implements CommandHandlerInterface
{
    use NormalizesVenueImageDescription;

    public const STORAGE_DIRECTORY = 'venues';

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_SIZE_BYTES = 8 * 1024 * 1024; // 8 MB

    public function __construct(
        private readonly VenueRepositoryInterface $venues,
        private readonly VenueImageRepositoryInterface $images,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddVenueImage);

        $venue = $this->venues->findById($command->venueId);
        if ($venue === null) {
            throw new NotFoundException("Veranstaltungsort \"{$command->venueId}\" wurde nicht gefunden.");
        }

        $description = $this->normalizeDescription($command->description);

        $file = $command->uploadedFile;
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['image' => 'Das Bild konnte nicht hochgeladen werden.']);
        }
        if (!in_array($file['type'], self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException(['image' => 'Nur JPEG-, PNG-, WebP- oder GIF-Bilder sind erlaubt.']);
        }
        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['image' => 'Das Bild darf höchstens 8 MB groß sein.']);
        }

        $imageId = Uuid::v4();
        $stored = $this->fileStorage->storeUploadedFile($file, self::STORAGE_DIRECTORY . '/' . $venue->id, $imageId);

        $existing = $this->images->findByVenue($venue->id);
        $position = $existing === [] ? 0 : max(array_map(static fn (VenueImage $image) => $image->position, $existing)) + 1;

        $image = new VenueImage(
            id: $imageId,
            venueId: $venue->id,
            filePath: $venue->id . '/' . $stored['storedFilename'],
            description: $description,
            position: $position,
            createdAt: new DateTimeImmutable(),
        );
        $this->images->save($image);

        return $image->id;
    }
}
