<?php

declare(strict_types=1);

namespace App\Application\Insurance\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\Rental\Query\GetRental;
use App\Application\Rental\Query\RentalDetail;
use App\Domain\Article\Article;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Insurance\CurrentValueRepositoryInterface;
use App\Domain\Insurance\CurrentValueResolver;
use App\Domain\Insurance\RentalInsuranceVehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleRepositoryInterface;

final class GetRentalInsuranceListHandler implements QueryHandlerInterface
{
    /** @var array<string, string|null> */
    private array $categoryNames = [];

    public function __construct(
        private readonly QueryHandlerInterface $getRental, // GetRentalHandler
        private readonly CategoryRepositoryInterface $categories,
        private readonly CurrentValueRepositoryInterface $currentValues,
        private readonly ?VehicleRepositoryInterface $vehicles = null,
        private readonly ?RentalInsuranceVehicleRepositoryInterface $insuranceVehicles = null,
    ) {
    }

    public function handle(QueryInterface $query): RentalInsuranceList
    {
        assert($query instanceof GetRentalInsuranceList);

        $detail = $this->getRental->handle(new GetRental($query->rentalId));
        assert($detail instanceof RentalDetail);

        $articleIds = array_merge(
            array_map(static fn ($i) => $i->article->id, $detail->issuedItems),
            array_map(static fn ($i) => $i->article->id, $detail->items),
        );
        $fixedValues = $this->currentValues->articleFixedValues($articleIds);

        $rows = [];
        foreach ($detail->issuedItems as $issued) {
            $resolved = CurrentValueResolver::resolve($issued->inventoryNumber, $fixedValues[$issued->article->id] ?? null);
            $rows[] = new InsuranceListRow(
                articleId: $issued->article->id,
                inventoryNumberId: $issued->inventoryNumber->id,
                category: $this->categoryName($issued->article),
                manufacturerModel: $issued->displayName(),
                inventoryNumber: $issued->inventoryNumber->number,
                acquisitionYear: $issued->inventoryNumber->acquisitionDate !== null ? (int) $issued->inventoryNumber->acquisitionDate->format('Y') : null,
                currentValue: $resolved['value'],
                currentValueOrigin: $resolved['origin'],
                purchasePrice: $issued->inventoryNumber->effectivePurchasePrice($issued->article->purchasePrice),
                currentValueNote: $resolved['origin'] === CurrentValueResolver::ORIGIN_AI ? $issued->inventoryNumber->currentValueNote : null,
            );
        }

        if ($query->includeUnissued) {
            foreach ($detail->items as $entry) {
                // Bundles sind nur eine Klammer um ihre Bestandteile, kein eigenes Gerät.
                if ($entry->article->isBundle) {
                    continue;
                }
                $open = $entry->item->quantity - $entry->issuedCount;
                $resolved = CurrentValueResolver::resolve(null, $fixedValues[$entry->article->id] ?? null);
                for ($i = 0; $i < $open; $i++) {
                    $rows[] = new InsuranceListRow(
                        articleId: $entry->article->id,
                        inventoryNumberId: null,
                        category: $this->categoryName($entry->article),
                        manufacturerModel: $entry->displayName(),
                        inventoryNumber: null,
                        acquisitionYear: null,
                        currentValue: $resolved['value'],
                        currentValueOrigin: $resolved['origin'],
                        purchasePrice: $entry->article->purchasePrice,
                    );
                }
            }
        }

        // Nach Kategorie (ohne Kategorie zuletzt), Artikel und Inventarnummer
        // (nicht ausgegebene Exemplare ohne Nummer jeweils hinter den ausgegebenen).
        usort($rows, static fn (InsuranceListRow $a, InsuranceListRow $b): int =>
            ($a->category === null) <=> ($b->category === null)
            ?: strnatcasecmp((string) $a->category, (string) $b->category)
            ?: strnatcasecmp($a->manufacturerModel, $b->manufacturerModel)
            ?: ($a->inventoryNumber === null) <=> ($b->inventoryNumber === null)
            ?: strnatcasecmp((string) $a->inventoryNumber, (string) $b->inventoryNumber));

        // Vehicles always come after the equipment, sorted by name.
        foreach ($this->vehicleRows($detail->rental->id) as $row) {
            $rows[] = $row;
        }

        return new RentalInsuranceList($detail->rental, $detail->customer, $rows);
    }

    /** @return list<InsuranceListRow> */
    private function vehicleRows(string $rentalId): array
    {
        if ($this->vehicles === null || $this->insuranceVehicles === null) {
            return [];
        }
        $ids = $this->insuranceVehicles->vehicleIdsForRental($rentalId);
        if ($ids === []) {
            return [];
        }

        $vehicles = $this->vehicles->findByIds($ids);
        usort($vehicles, static fn ($a, $b): int => strnatcasecmp($a->name, $b->name));

        return array_map(static fn ($vehicle) => new InsuranceListRow(
            articleId: null,
            inventoryNumberId: null,
            category: 'Fahrzeug (' . $vehicle->category->label() . ')',
            manufacturerModel: $vehicle->name,
            inventoryNumber: $vehicle->licensePlate,
            acquisitionYear: $vehicle->acquisitionYear,
            currentValue: $vehicle->currentValue,
            currentValueOrigin: $vehicle->currentValue !== null ? CurrentValueResolver::ORIGIN_ITEM : null,
            purchasePrice: $vehicle->purchasePrice,
            vehicleId: $vehicle->id,
        ), $vehicles);
    }

    private function categoryName(Article $article): ?string
    {
        if ($article->categoryId === null) {
            return null;
        }
        if (!array_key_exists($article->categoryId, $this->categoryNames)) {
            $this->categoryNames[$article->categoryId] = $this->categories->findById($article->categoryId)?->name;
        }

        return $this->categoryNames[$article->categoryId];
    }
}
