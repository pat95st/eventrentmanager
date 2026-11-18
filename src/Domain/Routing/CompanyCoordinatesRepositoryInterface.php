<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/** Geocoded company address (company_settings.latitude/longitude). */
interface CompanyCoordinatesRepositoryInterface
{
    /** @return array{0: float, 1: float}|null */
    public function find(): ?array;

    public function store(float $latitude, float $longitude): void;
}
