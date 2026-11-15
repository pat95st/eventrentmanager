<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\MinuteProgram\MinuteProgramItem;
use App\Domain\MinuteProgram\MinuteProgramItemRepositoryInterface;

final class InMemoryMinuteProgramItemRepository implements MinuteProgramItemRepositoryInterface
{
    /** @var array<string, MinuteProgramItem> */
    private array $items = [];

    public function save(MinuteProgramItem $item): void
    {
        $this->items[$item->id] = clone $item;
    }

    public function findById(string $id): ?MinuteProgramItem
    {
        return isset($this->items[$id]) ? clone $this->items[$id] : null;
    }

    public function findByMinuteProgram(string $minuteProgramId): array
    {
        $result = array_values(array_filter($this->items, static fn (MinuteProgramItem $i): bool => $i->minuteProgramId === $minuteProgramId));
        usort($result, static fn (MinuteProgramItem $a, MinuteProgramItem $b): int => $a->sortOrder <=> $b->sortOrder);

        return array_map(static fn (MinuteProgramItem $i): MinuteProgramItem => clone $i, $result);
    }

    public function nextSortOrder(string $minuteProgramId): int
    {
        return count($this->findByMinuteProgram($minuteProgramId)) + 1;
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }
}
