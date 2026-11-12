<?php

declare(strict_types=1);

namespace App\Domain\RevenueGoal;

interface RevenueGoalRepositoryInterface
{
    /** @return RevenueGoal[] mit allen Jahresbeträgen, sortiert nach Name */
    public function findAll(): array;

    public function findById(string $id): ?RevenueGoal;

    /** Legt das Ziel an bzw. aktualisiert es und ersetzt dabei seine Jahresbeträge. */
    public function save(RevenueGoal $goal): void;

    public function delete(string $id): void;
}
