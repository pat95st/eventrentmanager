<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rental\RentalSetBooking;
use App\Domain\Rental\RentalSetBookingRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;

final class MariadbRentalSetBookingRepository implements RentalSetBookingRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_set_bookings WHERE rental_id = :rental_id ORDER BY created_at ASC');
        $stmt->execute(['rental_id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?RentalSetBooking
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_set_bookings WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(RentalSetBooking $booking): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rental_set_bookings (id, rental_id, set_id, quantity, upgrade_ids, created_at)
             VALUES (:id, :rental_id, :set_id, :quantity, :upgrade_ids, :created_at)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), upgrade_ids = VALUES(upgrade_ids)'
        );
        $stmt->execute([
            'id' => $booking->id,
            'rental_id' => $booking->rentalId,
            'set_id' => $booking->setId,
            'quantity' => $booking->quantity,
            'upgrade_ids' => $booking->upgradeIds !== [] ? implode(',', $booking->upgradeIds) : null,
            'created_at' => $booking->createdAt->format('Y-m-d H:i:s'),
        ]);

        $this->pdo->prepare('DELETE FROM rental_set_allocations WHERE booking_id = :id')->execute(['id' => $booking->id]);
        $insert = $this->pdo->prepare('INSERT INTO rental_set_allocations (id, booking_id, article_id, quantity) VALUES (:id, :booking_id, :article_id, :quantity)');
        foreach ($booking->allocations as $articleId => $quantity) {
            if ($quantity > 0) {
                $insert->execute(['id' => Uuid::v4(), 'booking_id' => $booking->id, 'article_id' => (string) $articleId, 'quantity' => $quantity]);
            }
        }
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rental_set_bookings WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function existsForSet(string $setId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rental_set_bookings WHERE set_id = :id');
        $stmt->execute(['id' => $setId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): RentalSetBooking
    {
        $stmt = $this->pdo->prepare('SELECT article_id, quantity FROM rental_set_allocations WHERE booking_id = :id');
        $stmt->execute(['id' => $row['id']]);
        $allocations = [];
        foreach ($stmt->fetchAll() as $allocation) {
            $allocations[(string) $allocation['article_id']] = (int) $allocation['quantity'];
        }

        return new RentalSetBooking(
            id: $row['id'],
            rentalId: $row['rental_id'],
            setId: $row['set_id'],
            quantity: (int) $row['quantity'],
            upgradeIds: $row['upgrade_ids'] !== null && $row['upgrade_ids'] !== '' ? explode(',', (string) $row['upgrade_ids']) : [],
            allocations: $allocations,
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
