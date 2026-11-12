<?php

declare(strict_types=1);

namespace App\Domain\Depreciation;

/**
 * Nutzungsdauer eines Artikels: die Angabe seiner Kategorie, sonst die der
 * nächsten übergeordneten Kategorie mit Angabe, sonst der Standardwert aus
 * den Abschreibungs-Einstellungen.
 */
final class UsefulLifeResolver
{
    /**
     * @param array<string, string|null> $parentIds Kategorie-ID => Eltern-ID
     * @param array<string, int> $categoryYears Kategorie-ID => Nutzungsdauer in Jahren
     */
    public function __construct(
        private readonly array $parentIds,
        private readonly array $categoryYears,
        private readonly int $defaultYears,
    ) {
    }

    public function forCategory(?string $categoryId): int
    {
        $seen = [];
        while ($categoryId !== null && !isset($seen[$categoryId])) {
            if (isset($this->categoryYears[$categoryId])) {
                return $this->categoryYears[$categoryId];
            }
            $seen[$categoryId] = true;
            $categoryId = $this->parentIds[$categoryId] ?? null;
        }

        return $this->defaultYears;
    }
}
