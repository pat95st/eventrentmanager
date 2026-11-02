<?php

declare(strict_types=1);

namespace App\Application\Rack\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rack\RackRepositoryInterface;

final class ListRackDeviceOptionsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly RackRepositoryInterface $racks,
    ) {
    }

    /**
     * @return list<array{label: string, heightUnits: int, installDepthCm: string|null, devices: list<array{id: string, number: string, inRack: string|null}>}>
     *         je 19-Zoll-Artikel (alphabetisch) seine aktiven Exemplare; inRack = Rack-ID, in dem das Gerät bereits steckt
     */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRackDeviceOptions);

        $groups = [];
        $manufacturers = [];
        foreach ($this->articles->search(null, null, [], 10000, 0, excludeBundles: true) as $article) {
            if (!$article->is19Inch) {
                continue;
            }
            $devices = [];
            foreach ($this->inventoryNumbers->findActiveByArticle($article->id) as $inventoryNumber) {
                $devices[] = [
                    'id' => $inventoryNumber->id,
                    'number' => $inventoryNumber->number,
                    'inRack' => $this->racks->findSlotByInventoryNumber($inventoryNumber->id)?->rackId,
                ];
            }
            if ($devices === []) {
                continue;
            }
            $manufacturerId = $article->manufacturerId;
            if ($manufacturerId !== null && !array_key_exists($manufacturerId, $manufacturers)) {
                $manufacturers[$manufacturerId] = $this->manufacturers->findById($manufacturerId);
            }
            $groups[] = [
                'label' => ArticleDisplayName::of($article, $manufacturerId !== null ? $manufacturers[$manufacturerId] : null),
                'heightUnits' => max(1, (int) ($article->heightUnits ?? 1)),
                'installDepthCm' => $article->installDepthCm,
                'devices' => $devices,
            ];
        }
        usort($groups, static fn ($a, $b) => strnatcasecmp($a['label'], $b['label']));

        return $groups;
    }
}
