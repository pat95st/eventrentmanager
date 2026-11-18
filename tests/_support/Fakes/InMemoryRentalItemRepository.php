<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Rental\RentalItem;
use App\Domain\Rental\RentalItemRepositoryInterface;

final class InMemoryRentalItemRepository implements RentalItemRepositoryInterface
{
    /** @var array<string, RentalItem> */
    public array $items = [];

    public function save(RentalItem $item): void
    {
        $this->items[$item->id] = $item;
    }

    public function findById(string $id): ?RentalItem
    {
        return $this->items[$id] ?? null;
    }

    public function findByRental(string $rentalId): array
    {
        return array_values(array_filter($this->items, static fn (RentalItem $i) => $i->rentalId === $rentalId));
    }

    public function findByRentalAndArticle(string $rentalId, string $articleId): ?RentalItem
    {
        foreach ($this->items as $item) {
            if ($item->rentalId === $rentalId && $item->articleId === $articleId) {
                return $item;
            }
        }

        return null;
    }

    public function findByArticle(string $articleId): array
    {
        return array_values(array_filter($this->items, static fn (RentalItem $i) => $i->articleId === $articleId));
    }

    public function isUsedByArticle(string $articleId): bool
    {
        return $this->findByArticle($articleId) !== [];
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }
}
