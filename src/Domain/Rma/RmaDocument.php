<?php

declare(strict_types=1);

namespace App\Domain\Rma;

use DateTimeImmutable;

final class RmaDocument
{
    public function __construct(
        public readonly string $id,
        public readonly string $rmaId,
        public readonly string $originalFilename,
        public readonly string $storedFilename, // Dateiname auf der Platte, unter storage/uploads/rma/<rmaId>/
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly DateTimeImmutable $uploadedAt,
    ) {
    }

    public function humanReadableSize(): string
    {
        $bytes = $this->sizeBytes;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
