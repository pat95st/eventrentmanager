<?php

declare(strict_types=1);

namespace App\Domain\Rental;

interface RentalCustomerChecklistTokenRepositoryInterface
{
    /** Upsert: ersetzt einen ggf. bereits bestehenden Token derselben Vermietung (UNIQUE auf rental_id). */
    public function save(RentalCustomerChecklistToken $token): void;

    public function findByRentalId(string $rentalId): ?RentalCustomerChecklistToken;

    public function findByToken(string $token): ?RentalCustomerChecklistToken;
}
