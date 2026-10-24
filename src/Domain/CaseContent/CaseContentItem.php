<?php

declare(strict_types=1);

namespace App\Domain\CaseContent;

use DateTimeImmutable;

/**
 * Eine "Packlisten"-Zeile eines konkreten Case-Exemplars: welcher Artikel
 * (auf Artikel-Ebene, nicht an eine konkrete Inventurnummer des Artikels
 * gebunden) in welcher Menge in diesem Case steckt.
 */
final class CaseContentItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $caseInventoryNumberId,
        public readonly string $articleId,
        public int $quantity,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
