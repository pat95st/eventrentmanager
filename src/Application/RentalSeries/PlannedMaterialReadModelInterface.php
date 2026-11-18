<?php

declare(strict_types=1);

namespace App\Application\RentalSeries;

use DateTimeImmutable;

/** Read access to planned material across rentals, for conflict warnings. */
interface PlannedMaterialReadModelInterface
{
    /** @return array<string, int> article id => planned quantity */
    public function plannedByRental(string $rentalId): array;

    /**
     * Planned quantities of rentals whose occupied period (pickup/start to
     * return/end, by day) overlaps [$from, $to], restricted to $articleIds.
     *
     * @param string[] $articleIds
     * @param string[] $excludeRentalIds
     * @return list<array{rentalId: string, articleId: string, quantity: int, from: DateTimeImmutable, to: DateTimeImmutable}>
     */
    public function plannedOverlapping(array $articleIds, DateTimeImmutable $from, DateTimeImmutable $to, array $excludeRentalIds): array;
}
