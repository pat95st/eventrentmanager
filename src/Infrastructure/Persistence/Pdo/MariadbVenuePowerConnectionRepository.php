<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Venue\CircuitBreakerRating;
use App\Domain\Venue\PowerConnection;
use App\Domain\Venue\PowerConnectionType;
use App\Domain\Venue\RcdTripCurrent;
use App\Domain\Venue\VenuePowerConnectionRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbVenuePowerConnectionRepository implements VenuePowerConnectionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(PowerConnection $connection): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO venue_power_connections (
                id, venue_id, connection_type, breaker_rating, rcd_trip_current, location, created_at
             ) VALUES (
                :id, :venue_id, :connection_type, :breaker_rating, :rcd_trip_current, :location, :created_at
             )
             ON DUPLICATE KEY UPDATE
                connection_type = :connection_type2, breaker_rating = :breaker_rating2,
                rcd_trip_current = :rcd_trip_current2, location = :location2'
        );

        $stmt->execute([
            'id' => $connection->id,
            'venue_id' => $connection->venueId,
            'connection_type' => $connection->type->value,
            'breaker_rating' => $connection->breakerRating->value,
            'rcd_trip_current' => $connection->rcdTripCurrent->value,
            'location' => $connection->location,
            'created_at' => $connection->createdAt->format('Y-m-d H:i:s'),
            'connection_type2' => $connection->type->value,
            'breaker_rating2' => $connection->breakerRating->value,
            'rcd_trip_current2' => $connection->rcdTripCurrent->value,
            'location2' => $connection->location,
        ]);
    }

    public function findById(string $id): ?PowerConnection
    {
        $stmt = $this->pdo->prepare('SELECT * FROM venue_power_connections WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByVenue(string $venueId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM venue_power_connections WHERE venue_id = :venue_id ORDER BY created_at ASC'
        );
        $stmt->execute(['venue_id' => $venueId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByVenues(array $venueIds): array
    {
        $grouped = [];
        foreach ($venueIds as $venueId) {
            $grouped[$venueId] = [];
        }

        if ($venueIds === []) {
            return $grouped;
        }

        $placeholders = implode(',', array_fill(0, count($venueIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM venue_power_connections WHERE venue_id IN ({$placeholders}) ORDER BY created_at ASC"
        );
        $stmt->execute(array_values($venueIds));

        foreach ($stmt->fetchAll() as $row) {
            $grouped[$row['venue_id']][] = $this->hydrate($row);
        }

        return $grouped;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM venue_power_connections WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): PowerConnection
    {
        return new PowerConnection(
            id: $row['id'],
            venueId: $row['venue_id'],
            type: PowerConnectionType::from($row['connection_type']),
            breakerRating: CircuitBreakerRating::from($row['breaker_rating']),
            rcdTripCurrent: RcdTripCurrent::from($row['rcd_trip_current']),
            location: $row['location'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
