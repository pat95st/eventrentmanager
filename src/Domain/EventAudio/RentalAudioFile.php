<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

/** Jingle bzw. weitere Audio-Datei einer Veranstaltung (Migration 135). */
final class RentalAudioFile
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public AudioFileKind $kind,
        public string $title,
        public ?string $description,
        public readonly string $originalFilename,
        public readonly string $storedFilename,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public int $sortOrder,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function storageDirectory(string $rentalId): string
    {
        return 'veranstaltungs-audio/' . $rentalId;
    }
}
