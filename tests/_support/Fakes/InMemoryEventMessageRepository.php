<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\EventMessage\EventMessage;
use App\Domain\EventMessage\EventMessageRepositoryInterface;

final class InMemoryEventMessageRepository implements EventMessageRepositoryInterface
{
    /** @var array<string, EventMessage> */
    private array $messages = [];

    public function save(EventMessage $message): void
    {
        $this->messages[$message->id] = clone $message;
    }

    public function findById(string $id): ?EventMessage
    {
        return isset($this->messages[$id]) ? clone $this->messages[$id] : null;
    }

    public function findActiveByRental(string $rentalId): array
    {
        $result = array_values(array_filter($this->messages, static fn (EventMessage $m): bool => $m->rentalId === $rentalId && $m->isActive()));
        usort($result, static fn (EventMessage $a, EventMessage $b): int => $a->createdAt <=> $b->createdAt);

        return array_map(static fn (EventMessage $m): EventMessage => clone $m, $result);
    }

    public function findRecentByRental(string $rentalId, int $closedLimit = 10): array
    {
        $closed = array_values(array_filter($this->messages, static fn (EventMessage $m): bool => $m->rentalId === $rentalId && !$m->isActive()));
        usort($closed, static fn (EventMessage $a, EventMessage $b): int => $b->closedAt <=> $a->closedAt);

        return array_merge(
            array_reverse($this->findActiveByRental($rentalId)),
            array_map(static fn (EventMessage $m): EventMessage => clone $m, array_slice($closed, 0, max(0, $closedLimit))),
        );
    }
}
