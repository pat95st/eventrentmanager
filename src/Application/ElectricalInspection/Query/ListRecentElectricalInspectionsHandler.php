<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\ElectricalInspection\ElectricalInspectionRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;

final class ListRecentElectricalInspectionsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ElectricalInspectionRepositoryInterface $inspections,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    /** @return list<RecentElectricalInspection> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRecentElectricalInspections);

        $labels = [];
        $numbers = [];
        $result = [];
        foreach ($this->inspections->findRecent($query->limit, $query->search) as $inspection) {
            $id = $inspection->inventoryNumberId;
            if (!array_key_exists($id, $numbers)) {
                $numbers[$id] = $this->inventoryNumbers->findById($id);
            }
            $inventoryNumber = $numbers[$id];
            if ($inventoryNumber === null) {
                continue;
            }
            if (!array_key_exists($inventoryNumber->articleId, $labels)) {
                $article = $this->articles->findById($inventoryNumber->articleId);
                $manufacturer = $article?->manufacturerId !== null ? $this->manufacturers->findById($article->manufacturerId) : null;
                $labels[$inventoryNumber->articleId] = $article !== null ? ArticleDisplayName::of($article, $manufacturer) : '–';
            }
            $result[] = new RecentElectricalInspection($inspection, $id, $inventoryNumber->number, $labels[$inventoryNumber->articleId]);
        }

        return $result;
    }
}
