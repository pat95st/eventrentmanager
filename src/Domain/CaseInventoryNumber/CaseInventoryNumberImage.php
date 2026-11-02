<?php

declare(strict_types=1);

namespace App\Domain\CaseInventoryNumber;

use DateTimeImmutable;

/**
 * Foto eines befüllten Case-Exemplars (Migration 114) — zeigt, wie die Teile
 * eingeräumt werden; erscheint auf der Stückliste (PDF).
 */
final class CaseInventoryNumberImage
{
    public function __construct(
        public readonly string $id,
        public readonly string $caseInventoryNumberId,
        public readonly string $filePath, // relativ zu storage/cases, z. B. "<exemplar-id>/<bild-id>.jpg"
        public int $position,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
