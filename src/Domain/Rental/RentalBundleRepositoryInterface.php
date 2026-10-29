<?php

declare(strict_types=1);

namespace App\Domain\Rental;

interface RentalBundleRepositoryInterface
{
    /** @return RentalBundle[] */
    public function findByRental(string $rentalId): array;

    public function findById(string $id): ?RentalBundle;

    public function findByRentalAndBundle(string $rentalId, string $bundleArticleId): ?RentalBundle;

    public function save(RentalBundle $bundle): void;

    public function delete(string $id): void;

    /** Ob das Bundle in irgendeiner Vermietung gebucht ist. */
    public function existsForBundle(string $bundleArticleId): bool;
}
