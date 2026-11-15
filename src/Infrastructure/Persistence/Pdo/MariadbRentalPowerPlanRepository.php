<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\PowerPlan\PowerAssignment;
use App\Domain\PowerPlan\RentalPowerPlan;
use App\Domain\PowerPlan\RentalPowerPlanRepositoryInterface;
use PDO;

final class MariadbRentalPowerPlanRepository implements RentalPowerPlanRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByRental(string $rentalId): RentalPowerPlan
    {
        $stmt = $this->pdo->prepare('SELECT simultaneity_factor FROM rental_power_plans WHERE rental_id = :id');
        $stmt->execute(['id' => $rentalId]);
        $factor = $stmt->fetchColumn();

        $rows = $this->pdo->prepare('SELECT * FROM rental_power_assignments WHERE rental_id = :id');
        $rows->execute(['id' => $rentalId]);
        $assignments = [];
        foreach ($rows->fetchAll() as $row) {
            $assignment = new PowerAssignment(
                articleId: $row['article_id'],
                unitNo: (int) $row['unit_no'],
                targetArticleId: $row['target_article_id'] ?? null,
                targetUnitNo: $row['target_unit_no'] !== null ? (int) $row['target_unit_no'] : null,
                outletId: $row['outlet_id'] ?? null,
                venueConnectionId: $row['venue_power_connection_id'] ?? null,
                phase: $row['phase'] ?? null,
                simultaneityFactor: $row['simultaneity_factor'] !== null ? (float) $row['simultaneity_factor'] : null,
            );
            $assignments[$assignment->key()] = $assignment;
        }

        return new RentalPowerPlan($rentalId, $factor !== false ? (float) $factor : RentalPowerPlan::DEFAULT_FACTOR, $assignments);
    }

    public function save(RentalPowerPlan $plan): void
    {
        $started = !$this->pdo->inTransaction();
        if ($started) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->pdo->prepare(
                'INSERT INTO rental_power_plans (rental_id, simultaneity_factor) VALUES (:id, :factor)
                 ON DUPLICATE KEY UPDATE simultaneity_factor = VALUES(simultaneity_factor)'
            )->execute(['id' => $plan->rentalId, 'factor' => $plan->simultaneityFactor]);
            $this->pdo->prepare('DELETE FROM rental_power_assignments WHERE rental_id = :id')->execute(['id' => $plan->rentalId]);
            foreach ($plan->assignments as $assignment) {
                $this->saveAssignment($plan->rentalId, $assignment);
            }
            if ($started) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($started) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function saveAssignment(string $rentalId, PowerAssignment $assignment): void
    {
        $this->pdo->prepare(
            'REPLACE INTO rental_power_assignments
                (rental_id, article_id, unit_no, target_article_id, target_unit_no, outlet_id, venue_power_connection_id, phase, simultaneity_factor)
             VALUES (:rental_id, :article_id, :unit_no, :target_article_id, :target_unit_no, :outlet_id, :venue_id, :phase, :factor)'
        )->execute([
            'rental_id' => $rentalId,
            'article_id' => $assignment->articleId,
            'unit_no' => $assignment->unitNo,
            'target_article_id' => $assignment->isOutlet() ? $assignment->targetArticleId : null,
            'target_unit_no' => $assignment->isOutlet() ? $assignment->targetUnitNo : null,
            'outlet_id' => $assignment->isOutlet() ? $assignment->outletId : null,
            'venue_id' => $assignment->isVenue() ? $assignment->venueConnectionId : null,
            'phase' => $assignment->phase,
            'factor' => $assignment->simultaneityFactor,
        ]);
    }

    public function inputPowerOf(string $articleId): ?int
    {
        return $this->inputPowers([$articleId])[$articleId] ?? null;
    }

    public function inputPowers(array $articleIds): array
    {
        $articleIds = array_values(array_unique($articleIds));
        if ($articleIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id, input_power_w FROM articles WHERE input_power_w IS NOT NULL AND id IN ($placeholders)");
        $stmt->execute($articleIds);
        $powers = [];
        foreach ($stmt->fetchAll() as $row) {
            $powers[$row['id']] = (int) $row['input_power_w'];
        }

        return $powers;
    }

    public function saveInputPower(string $articleId, ?int $watts): void
    {
        $this->pdo->prepare('UPDATE articles SET input_power_w = :watts WHERE id = :id')->execute(['watts' => $watts, 'id' => $articleId]);
    }
}
