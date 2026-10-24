<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

use DateTimeImmutable;

/**
 * Ordnet einen Dokumenttyp einem Ziellaufwerk mit einer konkreten
 * Ablagepfad-Vorlage zu (siehe DocumentStoragePathResolver für die
 * unterstützten Platzhalter). Mehrere Zuordnungen pro Dokumenttyp sind
 * ausdrücklich möglich (mehrere Ziellaufwerke gleichzeitig).
 */
final class DocumentStorageAssignment
{
    public function __construct(
        public readonly string $id,
        public DocumentType $documentType,
        public string $storageTargetId,
        public string $targetPath,
        public bool $enabled,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
