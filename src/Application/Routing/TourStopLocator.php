<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Domain\Tour\TourStop;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\Geocoding\GeocoderInterface;

/**
 * Coordinates of a tour stop or waypoint: the linked venue's coordinates,
 * otherwise the stored ones, otherwise geocoded (venue address, the free
 * text location such as "Kirche Leinburg", or the waypoint address). Found
 * coordinates are written to the stop; the caller saves it.
 */
final class TourStopLocator
{
    public function __construct(
        private readonly VenueRepositoryInterface $venues,
        private readonly ?GeocoderInterface $geocoder = null,
    ) {
    }

    /** @return array{0: float, 1: float}|null */
    public function locate(TourStop $stop, bool $allowGeocoding = true): ?array
    {
        if ($stop->isStop()) {
            $venue = $stop->venueId !== null ? $this->venues->findById($stop->venueId) : null;
            if ($venue !== null && $venue->latitude !== null && $venue->longitude !== null) {
                return $this->remember($stop, [(float) $venue->latitude, (float) $venue->longitude]);
            }
            if ($stop->hasCoordinates()) {
                return [(float) $stop->latitude, (float) $stop->longitude];
            }
            if (!$allowGeocoding || $this->geocoder === null) {
                return null;
            }
            $found = $venue !== null
                ? $this->safely(fn () => $this->geocoder?->geocode($venue->street, $venue->postalCode, $venue->city, $venue->country))
                : $this->safely(fn () => $this->geocoder?->geocodeQuery((string) $stop->locationText));

            return $found !== null ? $this->remember($stop, $found) : null;
        }

        if ($stop->hasCoordinates()) {
            return [(float) $stop->latitude, (float) $stop->longitude];
        }
        if (!$allowGeocoding || $this->geocoder === null) {
            return null;
        }
        $found = trim((string) $stop->city) !== ''
            ? $this->safely(fn () => $this->geocoder?->geocode((string) $stop->street, (string) $stop->postalCode, (string) $stop->city, $stop->country))
            : $this->safely(fn () => $this->geocoder?->geocodeQuery((string) $stop->label));

        return $found !== null ? $this->remember($stop, $found) : null;
    }

    /**
     * @param array{0: float, 1: float} $coordinates
     * @return array{0: float, 1: float}
     */
    private function remember(TourStop $stop, array $coordinates): array
    {
        $stop->latitude = $coordinates[0];
        $stop->longitude = $coordinates[1];

        return $coordinates;
    }

    /** @return array{0: float, 1: float}|null */
    private function safely(callable $lookup): ?array
    {
        try {
            $result = $lookup();

            return is_array($result) ? [(float) $result[0], (float) $result[1]] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
