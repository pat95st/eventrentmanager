<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandInterface;

/** Creates or updates a waypoint between stops. */
final class SaveTourWaypoint implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly ?string $waypointId,
        public readonly string $purpose,
        public readonly string $addressType,
        public readonly string $warehouseId,
        public readonly string $savedAddressId,
        public readonly string $label,
        public readonly string $street,
        public readonly string $postalCode,
        public readonly string $city,
        public readonly string $country,
        public readonly string $afterStopId, // '' = before the first stop
        public readonly string $arriveAt,
        public readonly string $departAt,
        public readonly string $note,
        public readonly bool $rememberAddress,
    ) {
    }
}
