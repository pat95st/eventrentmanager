<?php

declare(strict_types=1);

namespace App\Application\Depreciation;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Depreciation\DepreciationAsset;
use App\Domain\Depreciation\DepreciationCalculator;
use App\Domain\Depreciation\DepreciationRepositoryInterface;
use App\Domain\Depreciation\DepreciationSchedule;
use App\Domain\Depreciation\DepreciationSettings;
use App\Domain\Depreciation\UsefulLifeResolver;

/** Lesende Anfragen rund um die Abschreibungen (Migration 146). */
final class DepreciationQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly DepreciationRepositoryInterface $depreciation,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    public function handle(QueryInterface $query): mixed
    {
        return match (true) {
            $query instanceof GetDepreciationReport => $this->report($query->year),
            $query instanceof GetInventoryNumberDepreciation => $this->forInventoryNumber($query->inventoryNumberId),
            $query instanceof GetDepreciationSettings => [
                'settings' => $this->depreciation->getSettings(),
                'categoryYears' => $this->depreciation->categoryUsefulLives(),
            ],
            default => throw new \LogicException('Unbekannte Anfrage ' . $query::class),
        };
    }

    private function report(int $year): DepreciationReport
    {
        $settings = $this->depreciation->getSettings();
        $resolver = $this->resolver($settings);

        $rows = [];
        $incomplete = [];
        foreach ($this->depreciation->findAssets() as $asset) {
            $schedule = $this->schedule($asset, $settings, $resolver);
            if ($schedule === null) {
                // Verkaufte Exemplare ohne Angaben sind für das Jahr nicht mehr relevant.
                if ($asset->soldAt === null) {
                    $incomplete[] = $asset;
                }

                continue;
            }
            if ($schedule->coversYear($year)) {
                $rows[] = new DepreciationReportRow($asset, $schedule);
            }
        }

        return new DepreciationReport($year, $rows, $incomplete);
    }

    private function forInventoryNumber(string $inventoryNumberId): ?DepreciationSchedule
    {
        $asset = $this->depreciation->findAssets($inventoryNumberId)[0] ?? null;
        if ($asset === null) {
            return null;
        }
        $settings = $this->depreciation->getSettings();

        return $this->schedule($asset, $settings, $this->resolver($settings));
    }

    private function schedule(DepreciationAsset $asset, DepreciationSettings $settings, UsefulLifeResolver $resolver): ?DepreciationSchedule
    {
        if (!$asset->isDepreciable()) {
            return null;
        }

        return DepreciationCalculator::schedule(
            (string) $asset->purchasePrice,
            $asset->acquisitionDate,
            $resolver->forCategory($asset->categoryId),
            $settings,
            $asset->soldAt,
        );
    }

    private function resolver(DepreciationSettings $settings): UsefulLifeResolver
    {
        $parents = [];
        foreach ($this->categories->findAll() as $category) {
            $parents[$category->id] = $category->parentId;
        }

        return new UsefulLifeResolver($parents, $this->depreciation->categoryUsefulLives(), $settings->defaultUsefulLifeYears);
    }
}
