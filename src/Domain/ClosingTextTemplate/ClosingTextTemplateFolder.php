<?php

declare(strict_types=1);

namespace App\Domain\ClosingTextTemplate;

use DateTimeImmutable;

/**
 * Ordner zur Organisation von Textvorlagen für Schlusstexte (siehe
 * ClosingTextTemplate) — Baumstruktur analog App\Domain\Category\Category
 * (siehe dortiger Kommentar für die identische Begründung des Musters).
 */
final class ClosingTextTemplateFolder
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public ?string $parentId,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
