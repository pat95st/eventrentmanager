<?php

declare(strict_types=1);

namespace App\Domain\TravelCost;

use DateTimeImmutable;

/** Line of the travel cost section of a document (migration 182). */
final class TravelCostLine
{
    public function __construct(
        public readonly string $id,
        public string $description,
        public string $kilometers, // decimal as string, e.g. "312.00"
        public string $ratePerKm,  // EUR per km as string, e.g. "0.45"
        public ?string $detail = null,
        public ?DateTimeImmutable $tripDate = null,
        public int $sortOrder = 0,
    ) {
    }

    public function lineTotal(): float
    {
        return round((float) $this->kilometers * (float) $this->ratePerKm, 2);
    }
}
