<?php

declare(strict_types=1);

namespace App\Domain\Federation;

/**
 * Agreed partner discount for an event that was created by accepting a
 * partner's material request — applied as percentage discount when an
 * invoice is created from that event.
 */
interface PartnerRentalDiscountLookupInterface
{
    public function discountPercentForRental(string $rentalId): ?float;
}
