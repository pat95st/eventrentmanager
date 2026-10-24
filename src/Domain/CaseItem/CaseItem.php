<?php

declare(strict_types=1);

namespace App\Domain\CaseItem;

use DateTimeImmutable;

/**
 * Ein "Case" ist ein Transport-/Flightcase-Modell, ähnlich wie ein Artikel:
 * fester Hersteller (aus derselben Herstellerliste wie bei Artikeln) und ein
 * eigenes Präfix für die Bildung standardisierter Inventurnummern der
 * einzelnen physischen Exemplare (siehe CaseInventoryNumber).
 */
final class CaseItem
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public string $manufacturerId,
        public ?string $prefix, // Präfix für generierte Inventurnummern, z. B. "RCK"; bei Altdaten ggf. noch null
        public ?string $description,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
