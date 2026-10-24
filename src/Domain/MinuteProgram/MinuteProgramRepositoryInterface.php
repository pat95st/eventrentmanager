<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

interface MinuteProgramRepositoryInterface
{
    public function save(MinuteProgram $program): void;

    public function findById(string $id): ?MinuteProgram;

    /** @return MinuteProgram[] sortiert nach start_at aufsteigend */
    public function findByRental(string $rentalId): array;

    public function delete(string $id): void;
}
