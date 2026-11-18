<?php

declare(strict_types=1);

namespace App\Application\RentalSeries;

/**
 * Copies the child records of a rental (planned material, minute programs,
 * power plan, trades) into another rental. Every replace* method first
 * removes the target's existing records of that area ("replace" mode).
 */
interface RentalChildDataCopierInterface
{
    /** rental_items, rental_bundles (+ allocations), rental_set_bookings (+ allocations). */
    public function replaceMaterial(string $sourceRentalId, string $targetRentalId): void;

    /** Minute programs with their items; start times are moved by $dayOffset days, live state is reset. */
    public function replaceMinutePrograms(string $sourceRentalId, string $targetRentalId, int $dayOffset): void;

    /** Moves the start times of all minute programs of a rental by $dayOffset days. */
    public function shiftMinutePrograms(string $rentalId, int $dayOffset): void;

    /** Power plan; plug-ins into venue connections are kept only when both rentals share the venue. */
    public function replacePowerPlan(string $sourceRentalId, string $targetRentalId, bool $keepVenueConnections): void;

    /** Trades required for the event (rental_trades). */
    public function replaceTrades(string $sourceRentalId, string $targetRentalId): void;
}
