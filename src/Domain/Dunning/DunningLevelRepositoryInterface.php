<?php

declare(strict_types=1);

namespace App\Domain\Dunning;

interface DunningLevelRepositoryInterface
{
    /** @return DunningLevel[] Aufsteigend nach daysAfterDue sortiert. */
    public function findAllOrdered(): array;

    public function findById(string $id): ?DunningLevel;

    /** Für die Eindeutigkeitsprüfung beim Anlegen/Bearbeiten (siehe CreateDunningLevelHandler/UpdateDunningLevelHandler). */
    public function existsByDaysAfterDue(int $daysAfterDue, ?string $excludeId = null): bool;

    public function save(DunningLevel $level): void;

    public function delete(string $id): void;
}
