<?php

declare(strict_types=1);

namespace App\Domain\DryHire;

/** Staffelstufe: ab Tag $fromDay kostet jeder (weitere) Tag $percentage % des Dry-Hire-Tagesmietsatzes. */
final class DryHireTier
{
    public function __construct(
        public readonly int $fromDay,
        public readonly string $percentage,
    ) {
    }
}
