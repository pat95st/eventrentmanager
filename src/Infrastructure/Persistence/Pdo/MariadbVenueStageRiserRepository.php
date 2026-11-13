<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Venue\Riser;
use App\Domain\Venue\RiserShape;
use App\Domain\Venue\VenueStageRiserRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbVenueStageRiserRepository implements VenueStageRiserRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Riser $riser): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO venue_stage_risers (
                id, stage_id, name, shape, width_m, depth_m, height_m, center_x_m, center_y_m, rotation_deg, created_at
             ) VALUES (
                :id, :stage_id, :name, :shape, :width_m, :depth_m, :height_m, :center_x_m, :center_y_m, :rotation_deg, :created_at
             )
             ON DUPLICATE KEY UPDATE
                name = :name2, shape = :shape2, width_m = :width_m2, depth_m = :depth_m2, height_m = :height_m2,
                center_x_m = :center_x_m2, center_y_m = :center_y_m2, rotation_deg = :rotation_deg2'
        );

        $values = [
            'name' => $riser->name,
            'shape' => $riser->shape->value,
            'width_m' => self::decimal($riser->widthM),
            'depth_m' => $riser->depthM !== null ? self::decimal($riser->depthM) : null,
            'height_m' => self::decimal($riser->heightM),
            'center_x_m' => self::decimal($riser->centerXM),
            'center_y_m' => self::decimal($riser->centerYM),
            'rotation_deg' => $riser->rotationDeg,
        ];
        $params = [
            'id' => $riser->id,
            'stage_id' => $riser->stageId,
            'created_at' => $riser->createdAt->format('Y-m-d H:i:s'),
        ];
        foreach ($values as $column => $value) {
            $params[$column] = $value;
            $params[$column . '2'] = $value;
        }

        $stmt->execute($params);
    }

    public function findById(string $id): ?Riser
    {
        $stmt = $this->pdo->prepare('SELECT * FROM venue_stage_risers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByVenueGroupedByStage(string $venueId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.* FROM venue_stage_risers r
             JOIN venue_stages s ON s.id = r.stage_id
             WHERE s.venue_id = :venue_id
             ORDER BY r.created_at ASC, r.id ASC'
        );
        $stmt->execute(['venue_id' => $venueId]);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(string) $row['stage_id']][] = $this->hydrate($row);
        }

        return $grouped;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM venue_stage_risers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Riser
    {
        return new Riser(
            id: (string) $row['id'],
            stageId: (string) $row['stage_id'],
            name: $row['name'] !== null ? (string) $row['name'] : null,
            shape: RiserShape::from((string) $row['shape']),
            widthM: (float) $row['width_m'],
            depthM: $row['depth_m'] !== null ? (float) $row['depth_m'] : null,
            heightM: (float) $row['height_m'],
            centerXM: (float) $row['center_x_m'],
            centerYM: (float) $row['center_y_m'],
            rotationDeg: (int) $row['rotation_deg'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }

    private static function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
