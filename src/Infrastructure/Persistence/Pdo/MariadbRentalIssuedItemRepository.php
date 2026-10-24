<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rental\RentalIssuedItem;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRentalIssuedItemRepository implements RentalIssuedItemRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(RentalIssuedItem $item): void
    {
        // Echtes Upsert (statt reines INSERT), damit die Rückgabe-Checkliste
        // denselben Datensatz mit returned_at/function_test_passed aktualisieren kann.
        $stmt = $this->pdo->prepare(
            'INSERT INTO rental_issued_items (id, rental_id, article_id, inventory_number_id, issued_at, returned_at, function_test_passed, confirmed_by_customer_at)
             VALUES (:id, :rental_id, :article_id, :inventory_number_id, :issued_at, :returned_at, :function_test_passed, :confirmed_by_customer_at)
             ON DUPLICATE KEY UPDATE returned_at = :returned_at2, function_test_passed = :function_test_passed2, confirmed_by_customer_at = :confirmed_by_customer_at2'
        );

        $stmt->execute([
            'id' => $item->id,
            'rental_id' => $item->rentalId,
            'article_id' => $item->articleId,
            'inventory_number_id' => $item->inventoryNumberId,
            'issued_at' => $item->issuedAt->format('Y-m-d H:i:s'),
            'returned_at' => $item->returnedAt?->format('Y-m-d H:i:s'),
            'function_test_passed' => $item->functionTestPassed === null ? null : (int) $item->functionTestPassed,
            'confirmed_by_customer_at' => $item->confirmedByCustomerAt?->format('Y-m-d H:i:s'),
            'returned_at2' => $item->returnedAt?->format('Y-m-d H:i:s'),
            'function_test_passed2' => $item->functionTestPassed === null ? null : (int) $item->functionTestPassed,
            'confirmed_by_customer_at2' => $item->confirmedByCustomerAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?RentalIssuedItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_issued_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rii.* FROM rental_issued_items rii
             INNER JOIN inventory_numbers inv ON inv.id = rii.inventory_number_id
             WHERE rii.rental_id = :rental_id
             ORDER BY inv.number ASC'
        );
        $stmt->execute(['rental_id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findActiveByRentalAndInventoryNumber(string $rentalId, string $inventoryNumberId): ?RentalIssuedItem
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rental_issued_items
             WHERE rental_id = :rental_id AND inventory_number_id = :inventory_number_id AND returned_at IS NULL'
        );
        $stmt->execute(['rental_id' => $rentalId, 'inventory_number_id' => $inventoryNumberId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByInventoryNumber(string $inventoryNumberId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rii.* FROM rental_issued_items rii
             INNER JOIN rentals r ON r.id = rii.rental_id
             WHERE rii.inventory_number_id = :inventory_number_id
             ORDER BY r.start_date DESC'
        );
        $stmt->execute(['inventory_number_id' => $inventoryNumberId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countByRentalAndArticle(string $rentalId, string $articleId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rental_issued_items WHERE rental_id = :rental_id AND article_id = :article_id'
        );
        $stmt->execute(['rental_id' => $rentalId, 'article_id' => $articleId]);

        return (int) $stmt->fetchColumn();
    }

    public function isInventoryNumberIssued(string $inventoryNumberId, ?string $excludeRentalId = null): bool
    {
        // Zurückgegebene Ausgaben blockieren eine erneute Ausgabe derselben
        // physischen Einheit nicht mehr.
        $sql = 'SELECT COUNT(*) FROM rental_issued_items
                WHERE inventory_number_id = :inventory_number_id AND returned_at IS NULL';
        $params = ['inventory_number_id' => $inventoryNumberId];
        if ($excludeRentalId !== null) {
            $sql .= ' AND rental_id != :exclude_rental_id';
            $params['exclude_rental_id'] = $excludeRentalId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isInventoryNumberIssuedDuringRange(
        string $inventoryNumberId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        ?string $excludeRentalId = null,
    ): bool {
        $sql = 'SELECT COUNT(*) FROM rental_issued_items rii
                INNER JOIN rentals r ON r.id = rii.rental_id
                WHERE rii.inventory_number_id = :inventory_number_id
                  AND rii.returned_at IS NULL
                  AND r.start_date <= :to AND r.end_date >= :from';
        $params = [
            'inventory_number_id' => $inventoryNumberId,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ];
        if ($excludeRentalId !== null) {
            $sql .= ' AND rii.rental_id != :exclude_rental_id';
            $params['exclude_rental_id'] = $excludeRentalId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rental_issued_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteByRentalAndArticle(string $rentalId, string $articleId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM rental_issued_items WHERE rental_id = :rental_id AND article_id = :article_id'
        );
        $stmt->execute(['rental_id' => $rentalId, 'article_id' => $articleId]);
    }

    private function hydrate(array $row): RentalIssuedItem
    {
        return new RentalIssuedItem(
            id: $row['id'],
            rentalId: $row['rental_id'],
            articleId: $row['article_id'],
            inventoryNumberId: $row['inventory_number_id'],
            issuedAt: new DateTimeImmutable($row['issued_at']),
            returnedAt: $row['returned_at'] !== null ? new DateTimeImmutable($row['returned_at']) : null,
            functionTestPassed: $row['function_test_passed'] !== null ? (bool) $row['function_test_passed'] : null,
            confirmedByCustomerAt: $row['confirmed_by_customer_at'] !== null ? new DateTimeImmutable($row['confirmed_by_customer_at']) : null,
        );
    }
}
