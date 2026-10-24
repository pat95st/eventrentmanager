<?php

declare(strict_types=1);

namespace App\Domain\Venue;

use DateTimeImmutable;

final class Venue
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public string $street,        // Straße/Hausnummer
        public string $postalCode,
        public string $city,
        public ?string $country,
        public ?string $notes,
        public ?string $caretakerName,  // Hausmeister vor Ort, optional
        public ?string $caretakerPhone, // Telefonnummer des Hausmeisters, optional
        public ?float $latitude,   // per Geokodierung (OpenStreetMap Nominatim) ermittelt; kann fehlen
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
