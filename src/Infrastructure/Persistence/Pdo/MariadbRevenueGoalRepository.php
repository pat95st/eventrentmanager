<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\RevenueGoal\RevenueGoal;
use App\Domain\RevenueGoal\RevenueGoalKind;
use App\Domain\RevenueGoal\RevenueGoalRepositoryInterface;
use PDO;

final class MariadbRevenueGoalRepository implements RevenueGoalRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $goals = [];
        foreach ($this->pdo->query('SELECT id, name, kind, amount FROM revenue_goals ORDER BY name ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $goals[(string) $row['id']] = new RevenueGoal(
                (string) $row['id'],
                (string) $row['name'],
                RevenueGoalKind::tryFrom((string) $row['kind']) ?? RevenueGoalKind::Target,
                (string) $row['amount'],
            );
        }
        foreach ($this->pdo->query('SELECT revenue_goal_id, year, amount FROM revenue_goal_year_amounts ORDER BY year ASC')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $goal = $goals[(string) $row['revenue_goal_id']] ?? null;
            $goal?->setYearAmount((int) $row['year'], (string) $row['amount']);
        }

        return array_values($goals);
    }

    public function findById(string $id): ?RevenueGoal
    {
        foreach ($this->findAll() as $goal) {
            if ($goal->id === $id) {
                return $goal;
            }
        }

        return null;
    }

    public function save(RevenueGoal $goal): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'INSERT INTO revenue_goals (id, name, kind, amount, created_at, updated_at)
                 VALUES (:id, :name, :kind, :amount, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), kind = VALUES(kind), amount = VALUES(amount), updated_at = NOW()'
            )->execute(['id' => $goal->id, 'name' => $goal->name, 'kind' => $goal->kind->value, 'amount' => $goal->amount]);

            $this->pdo->prepare('DELETE FROM revenue_goal_year_amounts WHERE revenue_goal_id = :id')->execute(['id' => $goal->id]);
            $stmt = $this->pdo->prepare('INSERT INTO revenue_goal_year_amounts (revenue_goal_id, year, amount) VALUES (:id, :year, :amount)');
            foreach ($goal->yearAmounts as $year => $amount) {
                $stmt->execute(['id' => $goal->id, 'year' => $year, 'amount' => $amount]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM revenue_goals WHERE id = :id')->execute(['id' => $id]);
    }
}
