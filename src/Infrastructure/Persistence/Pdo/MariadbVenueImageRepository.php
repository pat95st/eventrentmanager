<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Venue\VenueImage;
use App\Domain\Venue\VenueImageRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbVenueImageRepository implements VenueImageRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByVenue(string $venueId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM venue_images WHERE venue_id = :venue_id ORDER BY position, created_at');
        $stmt->execute(['venue_id' => $venueId]);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findById(string $id): ?VenueImage
    {
        $stmt = $this->pdo->prepare('SELECT * FROM venue_images WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(VenueImage $image): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO venue_images (id, venue_id, file_path, description, position, created_at)
             VALUES (:id, :venue_id, :file_path, :description, :position, :created_at)
             ON DUPLICATE KEY UPDATE description = VALUES(description), position = VALUES(position)'
        );
        $stmt->execute([
            'id' => $image->id,
            'venue_id' => $image->venueId,
            'file_path' => $image->filePath,
            'description' => $image->description,
            'position' => $image->position,
            'created_at' => $image->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM venue_images WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): VenueImage
    {
        return new VenueImage(
            id: (string) $row['id'],
            venueId: (string) $row['venue_id'],
            filePath: (string) $row['file_path'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            position: (int) $row['position'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
