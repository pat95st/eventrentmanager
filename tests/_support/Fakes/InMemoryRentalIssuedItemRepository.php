<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Rental\RentalIssuedItem;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;

/** In-memory test double for RentalIssuedItemRepositoryInterface (range checks are not modelled). */
final class InMemoryRentalIssuedItemRepository implements RentalIssuedItemRepositoryInterface
{
    /** @var array<string, RentalIssuedItem> */
    private array $items = [];

    public function save(RentalIssuedItem $item): void
    {
        $this->items[$item->id] = $item;
    }

    public function findById(string $id): ?RentalIssuedItem
    {
        return $this->items[$id] ?? null;
    }

    public function findByRental(string $rentalId): array
    {
        return array_values(array_filter($this->items, static fn (RentalIssuedItem $i) => $i->rentalId === $rentalId));
    }

    public function findActiveByRentalAndInventoryNumber(string $rentalId, string $inventoryNumberId): ?RentalIssuedItem
    {
        foreach ($this->items as $item) {
            if ($item->rentalId === $rentalId && $item->inventoryNumberId === $inventoryNumberId && $item->returnedAt === null) {
                return $item;
            }
        }

        return null;
    }

    public function findByInventoryNumber(string $inventoryNumberId): array
    {
        return array_values(array_filter($this->items, static fn (RentalIssuedItem $i) => $i->inventoryNumberId === $inventoryNumberId));
    }

    public function countByRentalAndArticle(string $rentalId, string $articleId): int
    {
        return count(array_filter($this->items, static fn (RentalIssuedItem $i) => $i->rentalId === $rentalId && $i->articleId === $articleId));
    }

    public function isInventoryNumberIssued(string $inventoryNumberId, ?string $excludeRentalId = null): bool
    {
        foreach ($this->items as $item) {
            if ($item->inventoryNumberId === $inventoryNumberId && $item->rentalId !== $excludeRentalId && $item->returnedAt === null) {
                return true;
            }
        }

        return false;
    }

    public function isInventoryNumberIssuedDuringRange(string $inventoryNumberId, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $excludeRentalId = null): bool
    {
        return $this->isInventoryNumberIssued($inventoryNumberId, $excludeRentalId);
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }

    public function deleteByRentalAndArticle(string $rentalId, string $articleId): void
    {
        foreach ($this->items as $id => $item) {
            if ($item->rentalId === $rentalId && $item->articleId === $articleId) {
                unset($this->items[$id]);
            }
        }
    }
}
