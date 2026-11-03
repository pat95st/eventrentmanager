<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use DateTimeImmutable;

/**
 * Für PDFs verfügbare Schriftfamilie (Migration 116). Dateinamen relativ zu
 * storage/uploads/fonts, z. B. "<id>/regular.ttf"; fehlende Schnitte werden
 * im PDF nachgebildet.
 */
final class PdfFont
{
    public const STYLES = ['regular', 'bold', 'italic', 'boldItalic'];

    public function __construct(
        public readonly string $id,
        public string $family,
        public string $source, // google | upload
        public string $regularFile,
        public ?string $boldFile,
        public ?string $italicFile,
        public ?string $boldItalicFile,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /** @return array<string, string|null> Schnitt => Datei */
    public function files(): array
    {
        return ['regular' => $this->regularFile, 'bold' => $this->boldFile, 'italic' => $this->italicFile, 'boldItalic' => $this->boldItalicFile];
    }

    /** @return string[] vorhandene Schnitte */
    public function availableStyles(): array
    {
        return array_keys(array_filter($this->files(), static fn ($file) => $file !== null));
    }
}
