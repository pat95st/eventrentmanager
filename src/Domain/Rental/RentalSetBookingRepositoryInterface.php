<?php

declare(strict_types=1);

namespace App\Domain\Rental;

interface RentalSetBookingRepositoryInterface
{
    /** @return RentalSetBooking[] */
    public function findByRental(string $rentalId): array;

    public function findById(string $id): ?RentalSetBooking;

    public function save(RentalSetBooking $booking): void;

    public function delete(string $id): void;

    public function existsForSet(string $setId): bool;
}
