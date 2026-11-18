<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\RentalSeries\PlannedMaterialReadModelInterface;
use DateTimeImmutable;

final class FakePlannedMaterialReadModel implements PlannedMaterialReadModelInterface
{
    /** @var array<string, array<string, int>> */
    public array $byRental = [];

    /** @var list<array{rentalId: string, articleId: string, quantity: int, from: DateTimeImmutable, to: DateTimeImmutable}> */
    public array $rows = [];

    public function plannedByRental(string $rentalId): array
    {
        return $this->byRental[$rentalId] ?? [];
    }

    public function plannedOverlapping(array $articleIds, DateTimeImmutable $from, DateTimeImmutable $to, array $excludeRentalIds): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (array $r) => in_array($r['articleId'], $articleIds, true)
                && !in_array($r['rentalId'], $excludeRentalIds, true)
                && $r['from'] <= $to && $r['to'] >= $from,
        ));
    }
}
