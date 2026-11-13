<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Venue\Stage;
use App\Domain\Venue\StageType;
use App\Domain\Venue\VenueStageRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbVenueStageRepository implements VenueStageRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Stage $stage): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO venue_stages (id, venue_id, name, width_m, depth_m, height_m, stage_type, created_at)
                 VALUES (:id, :venue_id, :name, :width_m, :depth_m, :height_m, :stage_type, :created_at)
                 ON DUPLICATE KEY UPDATE
                    name = :name2, width_m = :width_m2, depth_m = :depth_m2, height_m = :height_m2, stage_type = :stage_type2'
            );
            $stmt->execute([
                'id' => $stage->id,
                'venue_id' => $stage->venueId,
                'name' => $stage->name,
                'width_m' => $stage->widthM !== null ? self::decimal($stage->widthM) : null,
                'depth_m' => $stage->depthM !== null ? self::decimal($stage->depthM) : null,
                'height_m' => self::decimal($stage->heightM),
                'stage_type' => $stage->type->value,
                'created_at' => $stage->createdAt->format('Y-m-d H:i:s'),
                'name2' => $stage->name,
                'width_m2' => $stage->widthM !== null ? self::decimal($stage->widthM) : null,
                'depth_m2' => $stage->depthM !== null ? self::decimal($stage->depthM) : null,
                'height_m2' => self::decimal($stage->heightM),
                'stage_type2' => $stage->type->value,
            ]);

            $this->pdo->prepare('DELETE FROM venue_stage_power_distances WHERE stage_id = :stage_id')
                ->execute(['stage_id' => $stage->id]);

            $insert = $this->pdo->prepare(
                'INSERT INTO venue_stage_power_distances (stage_id, power_connection_id, distance_m)
                 VALUES (:stage_id, :power_connection_id, :distance_m)'
            );
            foreach ($stage->powerDistances as $connectionId => $distance) {
                $insert->execute([
                    'stage_id' => $stage->id,
                    'power_connection_id' => $connectionId,
                    'distance_m' => self::decimal($distance),
                ]);
            }

            $this->pdo->prepare('DELETE FROM venue_stage_elements WHERE stage_id = :stage_id')
                ->execute(['stage_id' => $stage->id]);

            $insertElement = $this->pdo->prepare(
                'INSERT INTO venue_stage_elements (stage_id, element_size, quantity) VALUES (:stage_id, :element_size, :quantity)'
            );
            foreach ($stage->elementStock as $elementSize => $quantity) {
                if ($quantity > 0) {
                    $insertElement->execute(['stage_id' => $stage->id, 'element_size' => $elementSize, 'quantity' => $quantity]);
                }
            }

            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function findById(string $id): ?Stage
    {
        $stmt = $this->pdo->prepare('SELECT * FROM venue_stages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateAll([$row])[0] : null;
    }

    public function findByVenue(string $venueId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM venue_stages WHERE venue_id = :venue_id ORDER BY created_at ASC, name ASC');
        $stmt->execute(['venue_id' => $venueId]);

        return $this->hydrateAll($stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM venue_stages WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Lädt Entfernungen und Elementbestand aller übergebenen Bühnen in je EINER Abfrage (kein N+1).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return list<Stage>
     */
    private function hydrateAll(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $distances = [];
        $ids = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT stage_id, power_connection_id, distance_m FROM venue_stage_power_distances WHERE stage_id IN ({$placeholders})"
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $distanceRow) {
            $distances[(string) $distanceRow['stage_id']][(string) $distanceRow['power_connection_id']] = (float) $distanceRow['distance_m'];
        }

        $elements = [];
        $stmt = $this->pdo->prepare(
            "SELECT stage_id, element_size, quantity FROM venue_stage_elements WHERE stage_id IN ({$placeholders})"
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $elementRow) {
            $elements[(string) $elementRow['stage_id']][(string) $elementRow['element_size']] = (int) $elementRow['quantity'];
        }

        $stages = [];
        foreach ($rows as $row) {
            $stages[] = new Stage(
                id: (string) $row['id'],
                venueId: (string) $row['venue_id'],
                name: (string) $row['name'],
                widthM: $row['width_m'] !== null ? (float) $row['width_m'] : null,
                depthM: $row['depth_m'] !== null ? (float) $row['depth_m'] : null,
                heightM: (float) $row['height_m'],
                type: StageType::from((string) $row['stage_type']),
                powerDistances: $distances[(string) $row['id']] ?? [],
                createdAt: new DateTimeImmutable((string) $row['created_at']),
                elementStock: $elements[(string) $row['id']] ?? [],
            );
        }

        return $stages;
    }

    private static function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
