<?php

declare(strict_types=1);

namespace App\Domain\Tour;

/**
 * Material of a tour: base list (rental items) plus deviations per stop.
 */
final class TourMaterialResolver
{
    /** @var array<string, array<string, TourStopItem>> stop id => article id => deviation */
    private array $byStop = [];

    /**
     * @param array<string, int> $base article id => base quantity
     * @param TourStopItem[] $items
     * @param TourStop[] $stops stops (events) in order
     */
    public function __construct(
        private readonly array $base,
        array $items,
        private readonly array $stops,
    ) {
        foreach ($items as $item) {
            $this->byStop[$item->stopId][$item->articleId] = $item;
        }
    }

    /** @return array<string, TourStopItem> article id => deviation at this stop */
    public function deviations(string $stopId): array
    {
        return $this->byStop[$stopId] ?? [];
    }

    /** @return array<string, int> article id => quantity needed at this stop */
    public function effective(string $stopId): array
    {
        $result = $this->base;
        foreach ($this->deviations($stopId) as $articleId => $item) {
            $result[$articleId] = max(0, ($result[$articleId] ?? 0) + $item->delta);
        }

        return array_filter($result, static fn (int $q) => $q > 0);
    }

    /**
     * Pieces that may be issued on top of the base list: per article the
     * largest addition any single stop needs.
     *
     * @return array<string, int>
     */
    public function extraForIssue(): array
    {
        $extra = [];
        foreach ($this->byStop as $items) {
            foreach ($items as $articleId => $item) {
                if ($item->delta > 0) {
                    $extra[$articleId] = max($extra[$articleId] ?? 0, $item->delta);
                }
            }
        }

        return $extra;
    }

    /**
     * Material loaded at the start of the tour: base list plus additions
     * that travel the whole tour.
     *
     * @return array<string, int>
     */
    public function startLoad(): array
    {
        $load = $this->base;
        $whole = [];
        foreach ($this->byStop as $items) {
            foreach ($items as $articleId => $item) {
                if ($item->delta > 0 && $item->allocation === TourAllocation::WholeTour) {
                    $whole[$articleId] = max($whole[$articleId] ?? 0, $item->delta);
                }
            }
        }
        foreach ($whole as $articleId => $quantity) {
            $load[$articleId] = ($load[$articleId] ?? 0) + $quantity;
        }

        return array_filter($load, static fn (int $q) => $q > 0);
    }

    /**
     * Differences between the material of the previous stop (or the base
     * list before the first stop) and this stop.
     *
     * @return list<array{articleId: string, before: int, after: int}>
     */
    public function changesAt(string $stopId): array
    {
        $previous = null;
        foreach ($this->stops as $stop) {
            if ($stop->id === $stopId) {
                break;
            }
            $previous = $stop;
        }
        $before = $previous !== null ? $this->effective($previous->id) : $this->base;
        $after = $this->effective($stopId);
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $articleId) {
            $b = $before[$articleId] ?? 0;
            $a = $after[$articleId] ?? 0;
            if ($a !== $b) {
                $changes[] = ['articleId' => (string) $articleId, 'before' => $b, 'after' => $a];
            }
        }

        return $changes;
    }

    /** @return string[] all article ids that occur in the base list or a deviation */
    public function articleIds(): array
    {
        $ids = array_keys($this->base);
        foreach ($this->byStop as $items) {
            $ids = array_merge($ids, array_keys($items));
        }

        return array_values(array_unique(array_map('strval', $ids)));
    }
}
