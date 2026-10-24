<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use DateTimeImmutable;

/**
 * Verplante Menge je Tag für einen Artikel im angefragten Zeitraum, auf
 * Grundlage der geplanten Vermietungsposten (RentalItem, nicht erst der
 * tatsächlichen Ausgabe) — für den Ampel-Verfügbarkeitskalender auf der
 * Artikel-Detailseite. Eine Menge zählt an jedem Tag im Zeitraum der
 * jeweiligen Vermietung, unabhängig davon, ob bereits konkrete
 * Inventurnummern dafür ausgegeben wurden.
 */
final class GetArticleAvailabilityHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly RentalItemRepositoryInterface $rentalItems,
        private readonly RentalRepositoryInterface $rentals,
    ) {
    }

    public function handle(QueryInterface $query): ArticleAvailability
    {
        assert($query instanceof GetArticleAvailability);

        $total = count($this->inventoryNumbers->findActiveByArticle($query->articleId));

        $from = new DateTimeImmutable($query->from);
        $to = new DateTimeImmutable($query->to);

        $reservedByDay = [];

        foreach ($this->rentalItems->findByArticle($query->articleId) as $item) {
            $rental = $this->rentals->findById($item->rentalId);
            if ($rental === null) {
                continue;
            }

            if ($rental->endDate < $from || $rental->startDate > $to) {
                continue;
            }

            $day = $rental->startDate > $from ? $rental->startDate : $from;
            $end = $rental->endDate < $to ? $rental->endDate : $to;

            while ($day <= $end) {
                $key = $day->format('Y-m-d');
                $reservedByDay[$key] = ($reservedByDay[$key] ?? 0) + $item->quantity;
                $day = $day->modify('+1 day');
            }
        }

        return new ArticleAvailability($total, $reservedByDay);
    }
}
