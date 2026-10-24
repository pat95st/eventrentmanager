<?php

declare(strict_types=1);

namespace App\Domain\Rental;

use DateTimeImmutable;

/**
 * Ein Eintrag der Ausgabe-Checkliste: eine konkrete, anhand der Inventarnummer
 * erfasste physische Einheit, die für diese Vermietung tatsächlich ausgegeben
 * wurde. articleId ist redundant zur Inventarnummer gespeichert, damit sich
 * "wie viele von Artikel X sind schon ausgegeben" ohne Zusatz-Join prüfen lässt.
 */
final class RentalIssuedItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $articleId,
        public readonly string $inventoryNumberId,
        public readonly DateTimeImmutable $issuedAt,
        // Rückgabe-Checkliste: wann zurückgenommen (null = noch nicht zurückgegeben)
        // und ob der Funktionstest bei der Rückgabe erfolgreich war (null = noch
        // kein Funktionstest durchgeführt, z. B. solange returnedAt noch null ist).
        public ?DateTimeImmutable $returnedAt = null,
        public ?bool $functionTestPassed = null,
        // Kunden-Checkliste (siehe CustomerChecklistController): wann der Kunde
        // selbst diesen Posten über seinen öffentlichen Link als "vorhanden"
        // bestätigt hat (null = noch nicht bestätigt bzw. Bestätigung wieder
        // zurückgenommen). Rein informativ, unabhängig von returnedAt/
        // functionTestPassed — wird bewusst NICHT in der internen
        // Rückgabe-Checkliste angezeigt.
        public ?DateTimeImmutable $confirmedByCustomerAt = null,
    ) {
    }
}
