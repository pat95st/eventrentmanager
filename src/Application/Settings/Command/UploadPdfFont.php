<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/** Eigene TTF-Dateien als Schriftfamilie hochladen (Alternative zu Google Fonts). */
final class UploadPdfFont implements CommandInterface
{
    /** @param array<string, array{name: string, type: string, tmp_name: string, error: int, size: int}|null> $files Schnitt (regular/bold/italic/boldItalic) => Upload */
    public function __construct(
        public readonly string $family,
        public readonly array $files,
    ) {
    }
}
