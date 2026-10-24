<?php

declare(strict_types=1);

namespace App\Application\Rma\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rma\RmaDocument;
use App\Domain\Rma\RmaDocumentRepositoryInterface;
use App\Domain\Rma\RmaRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AttachRmaDocumentHandler implements CommandHandlerInterface
{
    // Rechnungen, Lieferscheine und als PDF gespeicherte E-Mails abdecken,
    // ohne beliebige Dateitypen (z. B. ausführbare Dateien) zuzulassen.
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'message/rfc822', // rohe .eml-Datei einer E-Mail
    ];
    private const MAX_SIZE_BYTES = 15 * 1024 * 1024; // 15 MB

    public function __construct(
        private readonly RmaRepositoryInterface $rmas,
        private readonly RmaDocumentRepositoryInterface $rmaDocuments,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AttachRmaDocument);

        $rma = $this->rmas->findById($command->rmaId);
        if ($rma === null) {
            throw new NotFoundException("RMA-Vorgang \"{$command->rmaId}\" wurde nicht gefunden.");
        }

        $file = $command->uploadedFile;

        $mimeType = $this->detectMimeType($file);

        // fileinfo erkennt eine .eml-Datei oft nur als "text/plain" — anhand
        // der Endung nachhelfen, statt sie fälschlich abzulehnen.
        if ($mimeType === 'text/plain' && str_ends_with(strtolower($file['name']), '.eml')) {
            $mimeType = 'message/rfc822';
        }

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException([
                'document' => 'Nur PDF-, JPEG-, PNG-, WebP-Dateien oder .eml-E-Mails sind erlaubt.',
            ]);
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['document' => 'Die Datei darf höchstens 15 MB groß sein.']);
        }

        $documentId = Uuid::v4();
        $stored = $this->fileStorage->storeUploadedFile($file, 'rma/' . $rma->id, $documentId);

        $document = new RmaDocument(
            id: $documentId,
            rmaId: $rma->id,
            originalFilename: $stored['originalFilename'],
            storedFilename: $stored['storedFilename'],
            mimeType: $mimeType,
            sizeBytes: $stored['sizeBytes'],
            uploadedAt: new DateTimeImmutable(),
        );

        $this->rmaDocuments->add($document);

        return $document->id;
    }

    /** @param array{name: string, type: string, tmp_name: string} $file */
    private function detectMimeType(array $file): string
    {
        // Der vom Browser gemeldete Typ ist nicht vertrauenswürdig — wir prüfen
        // den tatsächlichen Dateiinhalt serverseitig nach.
        if (is_file($file['tmp_name']) && function_exists('mime_content_type')) {
            $detected = mime_content_type($file['tmp_name']);
            if ($detected !== false) {
                return $detected;
            }
        }

        return $file['type'] !== '' ? $file['type'] : 'application/octet-stream';
    }
}
