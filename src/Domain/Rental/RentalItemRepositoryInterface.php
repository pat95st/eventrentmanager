<?php

declare(strict_types=1);

namespace App\Domain\Rental;

interface RentalItemRepositoryInterface
{
    public function save(RentalItem $item): void;

    public function findById(string $id): ?RentalItem;

    /** @return RentalItem[] */
    public function findByRental(string $rentalId): array;

    public function findByRentalAndArticle(string $rentalId, string $articleId): ?RentalItem;

    /** Alle geplanten Posten eines Artikels über alle Vermietungen hinweg (für die Artikel-Detailseite). */
    public function findByArticle(string $articleId): array;

    /** Wird der Artikel noch in mindestens einer Vermietung geplant? (Löschsperre für Artikel) */
    public function isUsedByArticle(string $articleId): bool;

    public function delete(string $id): void;
}
