<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

use DateTimeImmutable;

/** Protokolliert einen einzelnen (Versuchs eines) Push auf ein Ziellaufwerk — siehe DocumentStoragePusher. */
final class DocumentStorageUploadLogEntry
{
    public function __construct(
        public readonly string $id,
        public readonly DocumentType $documentType,
        public readonly string $sourceId,
        public readonly ?string $assignmentId,
        public readonly ?string $storageTargetId,
        public readonly string $remotePath,
        public readonly UploadStatus $status,
        public readonly ?string $errorMessage,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
