<?php

declare(strict_types=1);

namespace App\Domain\Rental;

use DateTimeImmutable;

/**
 * Ein geplanter Artikel innerhalb einer Vermietung, auf Artikel-Ebene mit
 * Menge (nicht an eine konkrete Inventarnummer gebunden) — die tatsächliche
 * Ausgabe konkreter Inventarnummern erfolgt separat über RentalIssuedItem.
 */
final class RentalItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $articleId,
        public int $quantity,
        // Bei Artikeln mit Zubehör: wurde die Vollständigkeit des Zubehörs bei
        // der Ausgabe geprüft (siehe ConfirmRentalItemAccessoriesHandler)?
        // Bei Artikeln ohne Zubehör bleibt dies ohne Bedeutung.
        public bool $accessoryChecked,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
