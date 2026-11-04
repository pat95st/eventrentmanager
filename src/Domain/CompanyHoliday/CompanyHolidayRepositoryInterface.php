<?php

declare(strict_types=1);

namespace App\Domain\CompanyHoliday;

use DateTimeImmutable;

interface CompanyHolidayRepositoryInterface
{
    /** @return CompanyHoliday[] sortiert nach Beginn */
    public function findAll(): array;

    /** @return CompanyHoliday[] Urlaube, die [$from, $to] überschneiden, sortiert nach Beginn */
    public function findOverlapping(DateTimeImmutable $from, DateTimeImmutable $to): array;

    public function findById(string $id): ?CompanyHoliday;

    public function save(CompanyHoliday $holiday): void;

    public function delete(string $id): void;
}
