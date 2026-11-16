<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Technician\Trade;
use App\Domain\Technician\TradeRepositoryInterface;

final class InMemoryTradeRepository implements TradeRepositoryInterface
{
    /** @var array<string, Trade> */
    private array $trades = [];

    /** @var array<string, string[]> */
    private array $rentalTrades = [];

    public function findAll(): array
    {
        $trades = array_values($this->trades);
        usort($trades, static fn (Trade $a, Trade $b): int => [$a->sortOrder, $a->name] <=> [$b->sortOrder, $b->name]);

        return $trades;
    }

    public function findById(string $id): ?Trade
    {
        return $this->trades[$id] ?? null;
    }

    public function findByName(string $name): ?Trade
    {
        foreach ($this->trades as $trade) {
            if (mb_strtolower($trade->name) === mb_strtolower($name)) {
                return $trade;
            }
        }

        return null;
    }

    public function save(Trade $trade): void
    {
        $this->trades[$trade->id] = $trade;
    }

    public function delete(string $id): void
    {
        unset($this->trades[$id]);
    }

    public function tradeIdsForRental(string $rentalId): array
    {
        return $this->rentalTrades[$rentalId] ?? [];
    }

    public function saveTradeIdsForRental(string $rentalId, array $tradeIds): void
    {
        $this->rentalTrades[$rentalId] = array_values($tradeIds);
    }
}
