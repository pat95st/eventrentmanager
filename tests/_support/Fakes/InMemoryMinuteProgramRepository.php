<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;

final class InMemoryMinuteProgramRepository implements MinuteProgramRepositoryInterface
{
    /** @var array<string, MinuteProgram> */
    private array $programs = [];

    public function save(MinuteProgram $program): void
    {
        $this->programs[$program->id] = clone $program;
    }

    public function findById(string $id): ?MinuteProgram
    {
        return isset($this->programs[$id]) ? clone $this->programs[$id] : null;
    }

    public function findByRental(string $rentalId): array
    {
        $result = array_values(array_filter($this->programs, static fn (MinuteProgram $p): bool => $p->rentalId === $rentalId));
        usort($result, static fn (MinuteProgram $a, MinuteProgram $b): int => $a->startAt <=> $b->startAt);

        return array_map(static fn (MinuteProgram $p): MinuteProgram => clone $p, $result);
    }

    public function delete(string $id): void
    {
        unset($this->programs[$id]);
    }
}
