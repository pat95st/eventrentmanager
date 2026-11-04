<?php

declare(strict_types=1);

namespace App\Application\Rack\Query;

use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Rack\Rack;

/** Rack-Typ mit seinen Exemplaren (Migrationen 115/124). */
final class RackDetail
{
    /** @param RackExemplarDetail[] $exemplars */
    public function __construct(
        public readonly Rack $rack,
        public readonly ?Manufacturer $manufacturer,
        public readonly array $exemplars,
        public readonly ?string $nextInventoryNumberPreview = null,
        public readonly ?string $nextRackNumberPreview = null,
    ) {
    }

    public function exemplar(string $exemplarId): ?RackExemplarDetail
    {
        foreach ($this->exemplars as $exemplar) {
            if ($exemplar->exemplar->id === $exemplarId) {
                return $exemplar;
            }
        }

        return null;
    }
}
