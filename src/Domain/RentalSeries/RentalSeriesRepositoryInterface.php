<?php

declare(strict_types=1);

namespace App\Domain\RentalSeries;

interface RentalSeriesRepositoryInterface
{
    public function save(RentalSeries $series): void;

    public function findById(string $id): ?RentalSeries;

    /** @return RentalSeries[] newest first */
    public function findAll(): array;

    public function delete(string $id): void;
}
