<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

interface DocumentStorageUploadLogRepositoryInterface
{
    public function save(DocumentStorageUploadLogEntry $entry): void;

    /** @return DocumentStorageUploadLogEntry[] Neueste zuerst, für die Admin-Übersicht. */
    public function findRecent(int $limit = 100): array;

    /** @return DocumentStorageUploadLogEntry[] Neueste zuerst, für ein konkretes Dokument (z. B. auf dessen Detailseite). */
    public function findForDocument(DocumentType $documentType, string $sourceId): array;
}
