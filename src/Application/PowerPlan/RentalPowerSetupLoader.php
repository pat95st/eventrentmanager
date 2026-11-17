<?php

declare(strict_types=1);

namespace App\Application\PowerPlan;

use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\PowerDistributor\PowerDistributorRepositoryInterface;
use App\Domain\PowerPlan\PowerNode;
use App\Domain\PowerPlan\RentalPowerPlanRepositoryInterface;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Venue\VenuePowerConnectionRepositoryInterface;

/**
 * Builds the units of an event's power plan (migration 159) from its planned
 * articles: each unit of a power distributor and each unit of a device with
 * input power. Cables and bundles are skipped (bundles are planned via their
 * components); devices without input power are only counted.
 */
final class RentalPowerSetupLoader
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalItemRepositoryInterface $rentalItems,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CategoryRepositoryInterface $categories,
        private readonly PowerDistributorRepositoryInterface $distributors,
        private readonly VenuePowerConnectionRepositoryInterface $venueConnections,
        private readonly RentalPowerPlanRepositoryInterface $plans,
    ) {
    }

    public function load(string $rentalId): RentalPowerSetup
    {
        $rental = $this->rentals->findById($rentalId) ?? throw new NotFoundException('Veranstaltung wurde nicht gefunden.');
        $items = $this->rentalItems->findByRental($rentalId);
        $powers = $this->plans->articlePowers(array_map(static fn ($item) => $item->articleId, $items));
        $cableCategoryId = $this->categories->findCableCategoryId();

        $distributorNodes = [];
        $consumerNodes = [];
        $withoutPower = 0;
        foreach ($items as $item) {
            $article = $this->articles->findById($item->articleId);
            if ($article === null || $article->isBundle || ($cableCategoryId !== null && $article->categoryId === $cableCategoryId)) {
                continue;
            }
            $manufacturer = $article->manufacturerId !== null ? $this->manufacturers->findById($article->manufacturerId) : null;
            $name = ArticleDisplayName::of($article, $manufacturer);
            $distributor = $this->distributors->findByArticle($article->id);
            $articlePower = $powers[$article->id] ?? null;
            $power = $articlePower?->statedW();
            if ($distributor === null && ($power ?? 0) <= 0) {
                $withoutPower += $item->quantity;
                continue;
            }
            for ($unit = 1; $unit <= $item->quantity; $unit++) {
                $label = $name . ($item->quantity > 1 ? ' #' . $unit : '');
                $node = new PowerNode($article->id, $unit, $label, $distributor === null ? $power : null, $distributor, $distributor === null && ($articlePower?->isAverage() ?? false));
                if ($distributor !== null) {
                    $distributorNodes[] = $node;
                } else {
                    $consumerNodes[] = $node;
                }
            }
        }

        return new RentalPowerSetup(
            rental: $rental,
            plan: $this->plans->findByRental($rentalId),
            nodes: array_merge($distributorNodes, $consumerNodes),
            venueConnections: $rental->venueId !== null ? $this->venueConnections->findByVenue($rental->venueId) : [],
            unitsWithoutPower: $withoutPower,
        );
    }
}
