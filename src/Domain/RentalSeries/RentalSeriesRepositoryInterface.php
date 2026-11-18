<?php

declare(strict_types=1);

namespace App\Domain\RentalSeries;

interface RentalSeriesRepositoryInterface
{
    public function save(RentalSeries $series): void;

    public function findById(string $id): ?RentalSeries;

    public function delete(string $id): void;
}
