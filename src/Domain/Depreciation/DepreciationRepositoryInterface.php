<?php

declare(strict_types=1);

namespace App\Domain\Depreciation;

interface DepreciationRepositoryInterface
{
    public function getSettings(): DepreciationSettings;

    public function saveSettings(DepreciationSettings $settings): void;

    /** @return array<string, int> Kategorie-ID => abweichende Nutzungsdauer in Jahren */
    public function categoryUsefulLives(): array;

    /** @param array<string, int> $years Kategorie-ID => Nutzungsdauer; ersetzt alle bisherigen Angaben */
    public function replaceCategoryUsefulLives(array $years): void;

    /**
     * Alle Inventarnummern (auch verkaufte), sortiert nach Nummer — oder nur die eine angegebene.
     *
     * @return DepreciationAsset[]
     */
    public function findAssets(?string $inventoryNumberId = null): array;
}
