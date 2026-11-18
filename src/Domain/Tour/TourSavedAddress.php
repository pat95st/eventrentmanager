<?php

declare(strict_types=1);

namespace App\Domain\Tour;

/** Address remembered for waypoints (e.g. the usual hotel). */
final class TourSavedAddress
{
    public function __construct(
        public readonly string $id,
        public string $label,
        public string $street,
        public string $postalCode,
        public string $city,
        public ?string $country = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
    }
}
