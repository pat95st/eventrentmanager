<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\RentalSeries\RentalSeries;
use App\Domain\RentalSeries\RentalSeriesRepositoryInterface;

final class InMemoryRentalSeriesRepository implements RentalSeriesRepositoryInterface
{
    /** @var array<string, RentalSeries> */
    public array $series = [];

    public function save(RentalSeries $series): void
    {
        $this->series[$series->id] = $series;
    }

    public function findById(string $id): ?RentalSeries
    {
        return $this->series[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->series);
    }

    public function delete(string $id): void
    {
        unset($this->series[$id]);
    }
}
