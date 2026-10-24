<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rental\RentalItem;
use App\Domain\Rental\RentalItemRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRentalItemRepository implements RentalItemRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(RentalItem $item): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rental_items (id, rental_id, article_id, quantity, accessory_checked, created_at, updated_at)
             VALUES (:id, :rental_id, :article_id, :quantity, :accessory_checked, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE quantity = :quantity2, accessory_checked = :accessory_checked2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $item->id,
            'rental_id' => $item->rentalId,
            'article_id' => $item->articleId,
            'quantity' => $item->quantity,
            'accessory_checked' => (int) $item->accessoryChecked,
            'created_at' => $item->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $item->updatedAt->format('Y-m-d H:i:s'),
            'quantity2' => $item->quantity,
            'accessory_checked2' => (int) $item->accessoryChecked,
            'updated_at2' => $item->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?RentalItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ri.* FROM rental_items ri
             INNER JOIN articles a ON a.id = ri.article_id
             WHERE ri.rental_id = :rental_id
             ORDER BY a.name ASC'
        );
        $stmt->execute(['rental_id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByRentalAndArticle(string $rentalId, string $articleId): ?RentalItem
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rental_items WHERE rental_id = :rental_id AND article_id = :article_id'
        );
        $stmt->execute(['rental_id' => $rentalId, 'article_id' => $articleId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByArticle(string $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ri.* FROM rental_items ri
             INNER JOIN rentals r ON r.id = ri.rental_id
             WHERE ri.article_id = :article_id
             ORDER BY r.start_date DESC'
        );
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function isUsedByArticle(string $articleId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rental_items WHERE article_id = :article_id');
        $stmt->execute(['article_id' => $articleId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rental_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): RentalItem
    {
        return new RentalItem(
            id: $row['id'],
            rentalId: $row['rental_id'],
            articleId: $row['article_id'],
            quantity: (int) $row['quantity'],
            accessoryChecked: (bool) $row['accessory_checked'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
