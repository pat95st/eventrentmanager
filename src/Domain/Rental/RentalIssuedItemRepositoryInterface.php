<?php

declare(strict_types=1);

namespace App\Domain\Rental;

interface RentalIssuedItemRepositoryInterface
{
    public function save(RentalIssuedItem $item): void;

    public function findById(string $id): ?RentalIssuedItem;

    /** @return RentalIssuedItem[] */
    public function findByRental(string $rentalId): array;

    /**
     * Für den Barcode-Scanner-Workflow bei der Rückgabe: den noch nicht
     * zurückgegebenen Ausgabe-Eintrag dieser Vermietung zu einer Inventarnummer finden.
     */
    public function findActiveByRentalAndInventoryNumber(string $rentalId, string $inventoryNumberId): ?RentalIssuedItem;

    /**
     * Ausgabe-Historie einer einzelnen Inventurnummer, neueste zuerst.
     *
     * @return RentalIssuedItem[]
     */
    public function findByInventoryNumber(string $inventoryNumberId): array;

    /** Wie viele Exemplare eines Artikels sind innerhalb dieser Vermietung bereits ausgegeben. */
    public function countByRentalAndArticle(string $rentalId, string $articleId): int;

    /** Ist diese Inventarnummer bereits (in irgendeiner Vermietung) als ausgegeben erfasst. */
    public function isInventoryNumberIssued(string $inventoryNumberId, ?string $excludeRentalId = null): bool;

    /**
     * Ist diese Inventarnummer in einer anderen Vermietung erfasst, deren Zeitraum
     * sich mit [from, to] überschneidet (Doppelbelegungs-Schutz für den Kalender).
     */
    public function isInventoryNumberIssuedDuringRange(
        string $inventoryNumberId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?string $excludeRentalId = null,
    ): bool;

    public function delete(string $id): void;

    /** Löscht alle Ausgabe-Einträge einer Vermietung für einen bestimmten Artikel (z. B. wenn der Posten entfernt wird). */
    public function deleteByRentalAndArticle(string $rentalId, string $articleId): void;
}
