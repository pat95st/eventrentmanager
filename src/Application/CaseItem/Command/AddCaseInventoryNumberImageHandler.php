<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberImage;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberImageRepositoryInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

/** Siehe AddArticleImageHandler (gleiche Formate und Größengrenze). */
final class AddCaseInventoryNumberImageHandler implements CommandHandlerInterface
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_SIZE_BYTES = 8 * 1024 * 1024; // 8 MB

    public function __construct(
        private readonly CaseInventoryNumberRepositoryInterface $caseInventoryNumbers,
        private readonly CaseInventoryNumberImageRepositoryInterface $images,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddCaseInventoryNumberImage);

        $caseInventoryNumber = $this->caseInventoryNumbers->findById($command->caseInventoryNumberId);
        if ($caseInventoryNumber === null) {
            throw new NotFoundException("Case-Inventurnummer \"{$command->caseInventoryNumberId}\" wurde nicht gefunden.");
        }

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
        $stored = $this->fileStorage->storeUploadedFile($file, 'cases/' . $caseInventoryNumber->id, $imageId);

        $existing = $this->images->findByInventoryNumber($caseInventoryNumber->id);
        $position = $existing === [] ? 0 : max(array_map(static fn ($image) => $image->position, $existing)) + 1;

        $image = new CaseInventoryNumberImage(
            id: $imageId,
            caseInventoryNumberId: $caseInventoryNumber->id,
            filePath: $caseInventoryNumber->id . '/' . $stored['storedFilename'],
            position: $position,
            createdAt: new DateTimeImmutable(),
        );
        $this->images->save($image);

        return $image->id;
    }
}
