<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface PriceTierRepositoryInterface
{
    /** @return PriceTier[] sortiert nach Tagen aufsteigend, beginnend bei Tag 1; Anzahl variiert (siehe PriceTier::MAX_DAY_COUNT) */
    public function getAll(): array;

    /** @param PriceTier[] $tiers ersetzt die komplette bisherige Staffelung (auch bei einer Verkürzung, z. B. wenn die Anzahl Staffel-Tage reduziert wird) */
    public function saveAll(array $tiers): void;
}
