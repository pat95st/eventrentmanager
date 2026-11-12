<?php

declare(strict_types=1);

namespace App\Application\Depreciation;

use App\Application\Command\CommandInterface;

final class UpdateDepreciationSettings implements CommandInterface
{
    /**
     * @param array<string, string> $categoryYears Kategorie-ID => Eingabe (leer = keine abweichende Nutzungsdauer)
     */
    public function __construct(
        public readonly string $defaultUsefulLifeYears,
        public readonly string $lowValueThreshold,
        public readonly string $firstYearMode,
        public readonly string $residualValue,
        public readonly array $categoryYears,
    ) {
    }
}
