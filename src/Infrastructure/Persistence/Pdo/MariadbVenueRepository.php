<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Venue\Venue;
use App\Domain\Venue\VenueRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbVenueRepository implements VenueRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Venue $venue): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO venues (
                id, name, street, postal_code, city, country, notes, caretaker_name, caretaker_phone,
                latitude, longitude, created_at, updated_at
             ) VALUES (
                :id, :name, :street, :postal_code, :city, :country, :notes, :caretaker_name, :caretaker_phone,
                :latitude, :longitude, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                name = :name2, street = :street2, postal_code = :postal_code2, city = :city2,
                country = :country2, notes = :notes2, caretaker_name = :caretaker_name2,
                caretaker_phone = :caretaker_phone2, latitude = :latitude2, longitude = :longitude2,
                updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $venue->id,
            'name' => $venue->name,
            'street' => $venue->street,
            'postal_code' => $venue->postalCode,
            'city' => $venue->city,
            'country' => $venue->country,
            'notes' => $venue->notes,
            'caretaker_name' => $venue->caretakerName,
            'caretaker_phone' => $venue->caretakerPhone,
            'latitude' => $venue->latitude,
            'longitude' => $venue->longitude,
            'created_at' => $venue->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $venue->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $venue->name,
            'street2' => $venue->street,
            'postal_code2' => $venue->postalCode,
            'city2' => $venue->city,
            'country2' => $venue->country,
            'notes2' => $venue->notes,
            'caretaker_name2' => $venue->caretakerName,
            'caretaker_phone2' => $venue->caretakerPhone,
            'latitude2' => $venue->latitude,
            'longitude2' => $venue->longitude,
            'updated_at2' => $venue->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Venue
    {
        $stmt = $this->pdo->prepare('SELECT * FROM venues WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByName(string $name): ?Venue
    {
        $stmt = $this->pdo->prepare('SELECT * FROM venues WHERE LOWER(name) = LOWER(:name)');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $sql = 'SELECT * FROM venues' . $where . ' ORDER BY name ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
            $stmt->bindValue('offset', max(0, $offset ?? 0), PDO::PARAM_INT);
        }
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countAll(?string $search = null): int
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM venues' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(?string $search): array
    {
        if ($search === null || trim($search) === '') {
            return ['', []];
        }

        $needle = '%' . $search . '%';

        return [' WHERE name LIKE :search OR city LIKE :search2', ['search' => $needle, 'search2' => $needle]];
    }

    public function isUsedByRentals(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rentals WHERE venue_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM venues WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): Venue
    {
        return new Venue(
            id: $row['id'],
            name: $row['name'],
            street: $row['street'],
            postalCode: $row['postal_code'],
            city: $row['city'],
            country: $row['country'],
            notes: $row['notes'],
            caretakerName: $row['caretaker_name'],
            caretakerPhone: $row['caretaker_phone'],
            latitude: $row['latitude'] !== null ? (float) $row['latitude'] : null,
            longitude: $row['longitude'] !== null ? (float) $row['longitude'] : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
