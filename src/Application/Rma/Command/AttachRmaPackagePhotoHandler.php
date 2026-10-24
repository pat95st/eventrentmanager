<?php

declare(strict_types=1);

namespace App\Application\Rma\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rma\RmaPackagePhoto;
use App\Domain\Rma\RmaPackagePhotoRepositoryInterface;
use App\Domain\Rma\RmaRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

/** Analog zu AttachRmaDocumentHandler, aber bewusst nur Bilder (kein PDF/.eml) — siehe RmaPackagePhoto. */
final class AttachRmaPackagePhotoHandler implements CommandHandlerInterface
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_SIZE_BYTES = 8 * 1024 * 1024; // 8 MB

    public function __construct(
        private readonly RmaRepositoryInterface $rmas,
        private readonly RmaPackagePhotoRepositoryInterface $rmaPackagePhotos,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AttachRmaPackagePhoto);

        $rma = $this->rmas->findById($command->rmaId);
        if ($rma === null) {
            throw new NotFoundException("RMA-Vorgang \"{$command->rmaId}\" wurde nicht gefunden.");
        }

        $file = $command->uploadedFile;

        $mimeType = $this->detectMimeType($file);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException(['package_photo' => 'Nur JPEG-, PNG- oder WebP-Bilder sind erlaubt.']);
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['package_photo' => 'Das Foto darf höchstens 8 MB groß sein.']);
        }

        $photoId = Uuid::v4();
        $stored = $this->fileStorage->storeUploadedFile($file, 'rma-versandfotos/' . $rma->id, $photoId);

        $photo = new RmaPackagePhoto(
            id: $photoId,
            rmaId: $rma->id,
            originalFilename: $stored['originalFilename'],
            storedFilename: $stored['storedFilename'],
            mimeType: $mimeType,
            sizeBytes: $stored['sizeBytes'],
            uploadedAt: new DateTimeImmutable(),
        );

        $this->rmaPackagePhotos->add($photo);

        return $photo->id;
    }

    /** @param array{name: string, type: string, tmp_name: string} $file */
    private function detectMimeType(array $file): string
    {
        // Der vom Browser gemeldete Typ ist nicht vertrauenswürdig — wir prüfen
        // den tatsächlichen Dateiinhalt serverseitig nach (siehe AttachRmaDocumentHandler).
        if (is_file($file['tmp_name']) && function_exists('mime_content_type')) {
            $detected = mime_content_type($file['tmp_name']);
            if ($detected !== false) {
                return $detected;
            }
        }

        return $file['type'] !== '' ? $file['type'] : 'application/octet-stream';
    }
}
