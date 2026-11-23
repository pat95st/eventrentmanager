<?php

declare(strict_types=1);

namespace App\Application\Logbook;

use App\Application\Routing\CompanyLocator;
use App\Application\Routing\TourStopLocator;
use App\Domain\Exception\ValidationException;
use App\Domain\Logbook\LogbookLocation;
use App\Domain\Logbook\LogbookLocationType;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\Tour\TourSavedAddress;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use App\Infrastructure\Geocoding\GeocoderInterface;
use App\Shared\Uuid;

/**
 * Turns a start/destination choice of the capture form into a snapshot
 * (migration 184) and lists the choices.
 *
 * A choice is encoded as "type:refId" (e.g. "warehouse:<id>", "company:",
 * "custom:" with a free text). Coordinates come from the master data; missing
 * ones are geocoded once (best effort) so a route distance can be suggested.
 */
final class LogbookLocationResolver
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly VenueRepositoryInterface $venues,
        private readonly RentalRepositoryInterface $rentals,
        private readonly TourRepositoryInterface $tours,
        private readonly ?CompanyLocator $company = null,
        private readonly ?GeocoderInterface $geocoder = null,
        private readonly ?TourStopLocator $stopLocator = null,
    ) {
    }

    /**
     * @param string $field error key ("from"/"to")
     * @throws ValidationException for unknown choices
     */
    public function resolve(?string $choice, ?string $customText, ?string $rentalId, string $field, bool $saveCustomAddress = false): ?LogbookLocation
    {
        $choice = trim((string) $choice);
        if ($choice === '') {
            return null;
        }
        [$rawType, $refId] = array_pad(explode(':', $choice, 2), 2, '');
        $type = LogbookLocationType::tryFrom($rawType);
        $refId = $refId !== '' ? $refId : null;
        $invalid = new ValidationException([$field => 'Bitte einen gültigen Ort wählen.']);
        if ($type === null || ($type->needsReference() && $refId === null)) {
            throw $invalid;
        }

        $location = match ($type) {
            LogbookLocationType::Warehouse => $this->warehouse($refId),
            LogbookLocationType::EventVenue => $this->eventVenue($refId),
            LogbookLocationType::TourStop => $this->tourStop($refId, $rentalId),
            LogbookLocationType::Company => $this->companyLocation(),
            LogbookLocationType::SavedAddress => $this->savedAddress($refId),
            LogbookLocationType::Venue => $this->venue($refId),
            LogbookLocationType::Custom => $this->custom($customText, $field, $saveCustomAddress),
        };

        return $location ?? throw $invalid;
    }

    /**
     * Choices that do not depend on an event.
     *
     * @return list<array{group: string, options: list<array{value: string, label: string}>}>
     */
    public function generalOptions(): array
    {
        $groups = [];
        $warehouses = array_map(static fn ($w) => ['value' => 'warehouse:' . $w->id, 'label' => $w->name], $this->warehouses->findAll());
        if ($warehouses !== []) {
            $groups[] = ['group' => 'Lager', 'options' => $warehouses];
        }
        $groups[] = ['group' => 'Firma', 'options' => [['value' => 'company:', 'label' => 'Firmenadresse']]];
        $saved = array_map(static fn (TourSavedAddress $a) => ['value' => 'saved_address:' . $a->id, 'label' => $a->label], $this->tours->findSavedAddresses());
        if ($saved !== []) {
            $groups[] = ['group' => 'Gespeicherte Adressen', 'options' => $saved];
        }
        $venues = array_map(static fn ($v) => ['value' => 'venue:' . $v->id, 'label' => $v->name . ', ' . $v->city], $this->venues->findAll());
        if ($venues !== []) {
            $groups[] = ['group' => 'Veranstaltungsorte', 'options' => $venues];
        }

        return $groups;
    }

    /**
     * Choices of an event: its location, or the tour stops and waypoints of a
     * tour, plus the warehouses used for the templates "Hinfahrt"/"Rückfahrt".
     *
     * @return array{options: list<array{value: string, label: string}>, stops: list<array{id: string, label: string}>,
     *     outbound: array{from: ?string, to: ?string, date: string}, return: array{from: ?string, to: ?string, date: string}}
     */
    public function rentalOptions(Rental $rental): array
    {
        $options = [];
        $stops = [];
        $first = null;
        $last = null;
        if ($rental->isTour) {
            foreach ($this->tours->findStopsByRental($rental->id) as $stop) {
                $value = 'tour_stop:' . $stop->id;
                $options[] = ['value' => $value, 'label' => $stop->title()];
                if ($stop->isStop()) {
                    $stops[] = ['id' => $stop->id, 'label' => $stop->title()];
                    $first ??= $value;
                    $last = $value;
                }
            }
        } else {
            $label = $this->eventVenueLabel($rental);
            if ($label !== null) {
                $first = $last = 'event_venue:' . $rental->id;
                $options[] = ['value' => $first, 'label' => 'Veranstaltungsort: ' . $label];
            }
        }
        $pickup = $rental->pickupWarehouseId !== null ? 'warehouse:' . $rental->pickupWarehouseId : null;
        $return = ($rental->returnWarehouseId ?? $rental->pickupWarehouseId) !== null ? 'warehouse:' . ($rental->returnWarehouseId ?? $rental->pickupWarehouseId) : null;

        return [
            'options' => $options,
            'stops' => $stops,
            'outbound' => ['from' => $pickup, 'to' => $first, 'date' => ($rental->pickupAt ?? $rental->startDate)->format('Y-m-d')],
            'return' => ['from' => $last, 'to' => $return, 'date' => ($rental->returnAt ?? $rental->endDate)->format('Y-m-d')],
        ];
    }

    private function warehouse(?string $id): ?LogbookLocation
    {
        $warehouse = $id !== null ? $this->warehouses->findById($id) : null;
        if ($warehouse === null) {
            return null;
        }
        $coordinates = $warehouse->hasCoordinates() ? [$warehouse->latitude, $warehouse->longitude] : $this->geocode($warehouse->street, $warehouse->postalCode, $warehouse->city, $warehouse->country);

        return new LogbookLocation(LogbookLocationType::Warehouse, $warehouse->id, $warehouse->name, $warehouse->fullAddress(), $coordinates[0] ?? null, $coordinates[1] ?? null);
    }

    private function eventVenue(?string $rentalId): ?LogbookLocation
    {
        $rental = $rentalId !== null ? $this->rentals->findById($rentalId) : null;
        if ($rental === null) {
            return null;
        }
        $venue = $rental->venueId !== null ? $this->venues->findById($rental->venueId) : null;
        if ($venue !== null) {
            $location = $this->venueLocation($venue->id);

            return $location !== null ? new LogbookLocation(LogbookLocationType::EventVenue, $rental->id, $location->label, $location->address, $location->latitude, $location->longitude) : null;
        }
        $text = trim((string) $rental->eventLocation);
        if ($text === '') {
            return null;
        }
        $coordinates = $this->geocodeQuery($text);

        return new LogbookLocation(LogbookLocationType::EventVenue, $rental->id, $text, null, $coordinates[0] ?? null, $coordinates[1] ?? null);
    }

    private function tourStop(?string $stopId, ?string $rentalId): ?LogbookLocation
    {
        $stop = $stopId !== null ? $this->tours->findStop($stopId) : null;
        if ($stop === null || ($rentalId !== null && $stop->rentalId !== $rentalId)) {
            return null;
        }
        $coordinates = $this->stopLocator?->locate($stop) ?? ($stop->hasCoordinates() ? [(float) $stop->latitude, (float) $stop->longitude] : null);
        $address = $stop->isStop() ? null : ($stop->addressLine() !== '' ? $stop->addressLine() : null);

        return new LogbookLocation(LogbookLocationType::TourStop, $stop->id, $stop->title(), $address, $coordinates[0] ?? null, $coordinates[1] ?? null);
    }

    private function companyLocation(): ?LogbookLocation
    {
        $company = $this->company?->locate();
        if ($company === null) {
            return new LogbookLocation(LogbookLocationType::Company, null, 'Firmenadresse');
        }
        $address = trim($company['street'] . ', ' . trim($company['postalCode'] . ' ' . $company['city']), ' ,');

        return new LogbookLocation(LogbookLocationType::Company, null, (string) $company['label'], $address !== '' ? $address : null, $company['lat'], $company['lng']);
    }

    private function savedAddress(?string $id): ?LogbookLocation
    {
        $address = $id !== null ? $this->tours->findSavedAddress($id) : null;
        if ($address === null) {
            return null;
        }
        $line = trim($address->street . ', ' . trim($address->postalCode . ' ' . $address->city), ' ,');

        return new LogbookLocation(LogbookLocationType::SavedAddress, $address->id, $address->label, $line !== '' ? $line : null, $address->latitude, $address->longitude);
    }

    private function venue(?string $id): ?LogbookLocation
    {
        return $id !== null ? $this->venueLocation($id) : null;
    }

    private function venueLocation(string $id): ?LogbookLocation
    {
        $venue = $this->venues->findById($id);
        if ($venue === null) {
            return null;
        }
        $coordinates = $venue->hasCoordinates() ? [$venue->latitude, $venue->longitude] : $this->geocode($venue->street, $venue->postalCode, $venue->city, $venue->country);

        return new LogbookLocation(LogbookLocationType::Venue, $venue->id, $venue->name, $venue->fullAddress(), $coordinates[0] ?? null, $coordinates[1] ?? null);
    }

    private function custom(?string $text, string $field, bool $save): LogbookLocation
    {
        $text = trim((string) $text);
        if ($text === '') {
            throw new ValidationException([$field => 'Bitte die Adresse oder den Ort angeben.']);
        }
        if (mb_strlen($text) > 255) {
            throw new ValidationException([$field => 'Die Adresse darf höchstens 255 Zeichen lang sein.']);
        }
        $coordinates = $this->geocodeQuery($text);
        if (!$save) {
            return new LogbookLocation(LogbookLocationType::Custom, null, $text, null, $coordinates[0] ?? null, $coordinates[1] ?? null);
        }

        // "Street 1, 12345 City" is split into its parts; anything else is kept as street.
        $street = $text;
        $postalCode = '';
        $city = '';
        if (preg_match('/^(.+?),\s*(\d{4,5})\s+(.+)$/u', $text, $m) === 1) {
            [$street, $postalCode, $city] = [trim($m[1]), $m[2], trim($m[3])];
        }
        $saved = new TourSavedAddress(Uuid::v4(), $text, $street, $postalCode, $city, null, $coordinates[0] ?? null, $coordinates[1] ?? null);
        $this->tours->saveSavedAddress($saved);

        return new LogbookLocation(LogbookLocationType::SavedAddress, $saved->id, $saved->label, null, $saved->latitude, $saved->longitude);
    }

    private function eventVenueLabel(Rental $rental): ?string
    {
        $venue = $rental->venueId !== null ? $this->venues->findById($rental->venueId) : null;
        if ($venue !== null) {
            return $venue->name;
        }
        $text = trim((string) $rental->eventLocation);

        return $text !== '' ? $text : null;
    }

    /** @return array{0: float, 1: float}|null */
    private function geocode(string $street, string $postalCode, string $city, ?string $country): ?array
    {
        if ($this->geocoder === null || trim($city) === '') {
            return null;
        }
        try {
            $found = $this->geocoder->geocode($street, $postalCode, $city, $country);
        } catch (\Throwable) {
            return null;
        }

        return $found !== null ? [(float) $found[0], (float) $found[1]] : null;
    }

    /** @return array{0: float, 1: float}|null */
    private function geocodeQuery(string $query): ?array
    {
        if ($this->geocoder === null) {
            return null;
        }
        try {
            $found = $this->geocoder->geocodeQuery($query);
        } catch (\Throwable) {
            return null;
        }

        return $found !== null ? [(float) $found[0], (float) $found[1]] : null;
    }
}
