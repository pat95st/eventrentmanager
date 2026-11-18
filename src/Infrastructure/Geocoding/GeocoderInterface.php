<?php

declare(strict_types=1);

namespace App\Infrastructure\Geocoding;

interface GeocoderInterface
{
    /** @return array{0: float, 1: float}|null [Breitengrad, Längengrad] oder null, wenn nicht ermittelbar */
    public function geocode(string $street, string $postalCode, string $city, ?string $country): ?array;

    /**
     * Free-text search, e.g. "Kirche Leinburg" (tour stops without a saved venue).
     *
     * @return array{0: float, 1: float}|null
     */
    public function geocodeQuery(string $query): ?array;
}
