<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\NoticeDocument\NoticeDocument;
use App\Domain\NoticeDocument\NoticeDocumentArticleRepositoryInterface;
use App\Domain\NoticeDocument\NoticeDocumentRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Lädt ein generisches Hinweisdokument hoch (z. B. Bedienungsanleitung,
 * Sicherheitshinweis) und ordnet es optional direkt den übergebenen Artikeln
 * zu, denen es bei Buchung mitgeschickt werden soll (siehe
 * GetNoticeDocumentsForArticleIdsHandler).
 */
final class UploadNoticeDocumentHandler implements CommandHandlerInterface
{
    // Bewusst nur Dokumentformate, keine ausführbaren Dateien — ein
    // Hinweisdokument wird unverändert an Kunden weitergereicht.
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];
    private const MAX_SIZE_BYTES = 15 * 1024 * 1024; // 15 MB

    public function __construct(
        private readonly NoticeDocumentRepositoryInterface $documents,
        private readonly NoticeDocumentArticleRepositoryInterface $documentArticles,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof UploadNoticeDocument);

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für das Dokument angeben.']);
        }

        $file = $command->uploadedFile;
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['tmp_name'] ?? '') === '') {
            throw new ValidationException(['document' => 'Bitte eine Datei auswählen.']);
        }

        $mimeType = $this->detectMimeType($file);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException(['document' => 'Nur PDF-, JPEG-, PNG- oder WebP-Dateien sind erlaubt.']);
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['document' => 'Die Datei darf höchstens 15 MB groß sein.']);
        }

        $documentId = Uuid::v4();
        $stored = $this->fileStorage->storeUploadedFile($file, 'hinweisdokumente', $documentId);

        $document = new NoticeDocument(
            id: $documentId,
            name: $name,
            originalFilename: $stored['originalFilename'],
            storedFilename: $stored['storedFilename'],
            mimeType: $mimeType,
            sizeBytes: $stored['sizeBytes'],
            createdAt: new DateTimeImmutable(),
        );

        $this->documents->save($document);
        $this->documentArticles->replaceAssignments($document->id, $command->articleIds);

        return $document->id;
    }

    /** @param array{tmp_name: string, type: string} $file */
    private function detectMimeType(array $file): string
    {
        if (is_file($file['tmp_name']) && function_exists('mime_content_type')) {
            $detected = mime_content_type($file['tmp_name']);
            if ($detected !== false) {
                return $detected;
            }
        }

        return $file['type'] !== '' ? $file['type'] : 'application/octet-stream';
    }
}
