<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

interface MinuteProgramItemRepositoryInterface
{
    public function save(MinuteProgramItem $item): void;

    public function findById(string $id): ?MinuteProgramItem;

    /** @return MinuteProgramItem[] sortiert nach sort_order aufsteigend */
    public function findByMinuteProgram(string $minuteProgramId): array;

    public function nextSortOrder(string $minuteProgramId): int;

    public function delete(string $id): void;
}
