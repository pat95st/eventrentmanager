<?php

declare(strict_types=1);

namespace App\Domain\Warehouse;

use DateTimeImmutable;

/**
 * Ein Lagerort der Firma (z. B. Hauptlager, Außenlager) — Ausgabe und
 * Rücklieferung einer Vermietung können jeweils einem Lagerort zugeordnet
 * werden (siehe App\Domain\Rental\Rental::$pickupWarehouseId/
 * $returnWarehouseId). Bewusst als eigene, schlanke Stammdaten-Entität
 * analog App\Domain\Venue\Venue gehalten (gleiche Adressfelder samt
 * Geokodierung für den Routen-Button), aber ohne dessen
 * Stromanschluss-Verwaltung, die für einen Lagerort fachlich nicht relevant
 * ist.
 */
final class Warehouse
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public string $street,
        public string $postalCode,
        public string $city,
        public ?string $country,
        public ?string $notes,
        public ?float $latitude,  // per Geokodierung (OpenStreetMap Nominatim) ermittelt; kann fehlen
        public ?float $longitude,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function fullAddress(): string
    {
        $parts = [$this->street, trim($this->postalCode . ' ' . $this->city)];
        if ($this->country !== null && $this->country !== '') {
            $parts[] = $this->country;
        }

        return implode(', ', $parts);
    }
}
