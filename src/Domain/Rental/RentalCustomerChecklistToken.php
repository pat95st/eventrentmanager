<?php

declare(strict_types=1);

namespace App\Domain\Rental;

use DateTimeImmutable;

/**
 * Öffentlicher, token-gesicherter Link für genau eine Vermietung, über den
 * ein Kunde OHNE eigenes Benutzerkonto die für ihn ausgegebenen Artikel
 * abhaken kann (siehe CustomerChecklistController). Siehe Migration 071 für
 * die Begründung, $token bewusst im Klartext statt nur gehasht zu
 * speichern.
 */
final class RentalCustomerChecklistToken
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $token,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
