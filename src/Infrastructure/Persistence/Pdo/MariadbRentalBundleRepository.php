<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rental\RentalBundle;
use App\Domain\Rental\RentalBundleRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRentalBundleRepository implements RentalBundleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_bundles WHERE rental_id = :rental_id ORDER BY created_at ASC');
        $stmt->execute(['rental_id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?RentalBundle
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_bundles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRentalAndBundle(string $rentalId, string $bundleArticleId): ?RentalBundle
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_bundles WHERE rental_id = :rental_id AND bundle_article_id = :bundle_article_id');
        $stmt->execute(['rental_id' => $rentalId, 'bundle_article_id' => $bundleArticleId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(RentalBundle $bundle): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rental_bundles (id, rental_id, bundle_article_id, quantity, created_at, updated_at)
             VALUES (:id, :rental_id, :bundle_article_id, :quantity, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $bundle->id,
            'rental_id' => $bundle->rentalId,
            'bundle_article_id' => $bundle->bundleArticleId,
            'quantity' => $bundle->quantity,
            'created_at' => $bundle->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $bundle->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rental_bundles WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function existsForBundle(string $bundleArticleId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rental_bundles WHERE bundle_article_id = :id');
        $stmt->execute(['id' => $bundleArticleId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): RentalBundle
    {
        return new RentalBundle(
            id: $row['id'],
            rentalId: $row['rental_id'],
            bundleArticleId: $row['bundle_article_id'],
            quantity: (int) $row['quantity'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
