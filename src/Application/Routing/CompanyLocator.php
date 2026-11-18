<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Domain\Routing\CompanyCoordinatesRepositoryInterface;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\Geocoding\GeocoderInterface;

/** Coordinates of the company address, geocoded once and then stored. */
final class CompanyLocator
{
    public function __construct(
        private readonly CompanySettingsRepositoryInterface $company,
        private readonly CompanyCoordinatesRepositoryInterface $coordinates,
        private readonly ?GeocoderInterface $geocoder = null,
    ) {
    }

    /** @return array{label: string, street: string, postalCode: string, city: string, country: ?string, lat: ?float, lng: ?float}|null */
    public function locate(): ?array
    {
        $company = $this->company->get();
        if (trim((string) $company->city) === '') {
            return null;
        }
        $coordinates = $this->coordinates->find();
        if ($coordinates === null && $this->geocoder !== null) {
            try {
                $coordinates = $this->geocoder->geocode((string) $company->street, (string) $company->postalCode, (string) $company->city, $company->country);
            } catch (\Throwable) {
                $coordinates = null;
            }
            if ($coordinates !== null) {
                $this->coordinates->store($coordinates[0], $coordinates[1]);
            }
        }

        return [
            'label' => $company->name ?? 'Firma',
            'street' => (string) $company->street,
            'postalCode' => (string) $company->postalCode,
            'city' => (string) $company->city,
            'country' => $company->country,
            'lat' => $coordinates[0] ?? null,
            'lng' => $coordinates[1] ?? null,
        ];
    }
}
