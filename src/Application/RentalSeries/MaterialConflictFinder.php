<?php

declare(strict_types=1);

namespace App\Application\RentalSeries;

use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use DateTimeImmutable;

/**
 * Warns when planned material exceeds the inventory on any day of a period
 * (planned rental items of all overlapping rentals count). Only a warning,
 * planning is never blocked.
 */
final class MaterialConflictFinder
{
    /** @var array<string, string> */
    private array $nameCache = [];

    public function __construct(
        private readonly PlannedMaterialReadModelInterface $planned,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ?ArticleRepositoryInterface $articles = null,
        private readonly ?ManufacturerRepositoryInterface $manufacturers = null,
    ) {
    }

    /**
     * @param array<string, int> $needed article id => quantity
     * @param string[] $excludeRentalIds
     * @return MaterialConflict[]
     */
    public function find(array $needed, DateTimeImmutable $from, DateTimeImmutable $to, array $excludeRentalIds = []): array
    {
        $needed = array_filter($needed, static fn (int $quantity): bool => $quantity > 0);
        if ($needed === []) {
            return [];
        }
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        $articleIds = array_map('strval', array_keys($needed));
        $totals = $this->inventoryNumbers->countActiveByArticles($articleIds);

        /** @var array<string, array<string, int>> $byArticleDay */
        $byArticleDay = [];
        foreach ($this->planned->plannedOverlapping($articleIds, $from, $to, $excludeRentalIds) as $row) {
            $day = $row['from'] > $from ? $row['from']->setTime(0, 0) : $from;
            $end = $row['to'] < $to ? $row['to']->setTime(0, 0) : $to;
            for ($guard = 0; $day <= $end && $guard < 800; $guard++, $day = $day->modify('+1 day')) {
                $key = $day->format('Y-m-d');
                $byArticleDay[$row['articleId']][$key] = ($byArticleDay[$row['articleId']][$key] ?? 0) + $row['quantity'];
            }
        }

        $conflicts = [];
        foreach ($needed as $articleId => $quantity) {
            $articleId = (string) $articleId;
            $elsewhere = isset($byArticleDay[$articleId]) ? max($byArticleDay[$articleId]) : 0;
            $total = $totals[$articleId] ?? 0;
            if ($quantity + $elsewhere > $total) {
                $conflicts[] = new MaterialConflict($articleId, $this->name($articleId), $quantity, $elsewhere, $total);
            }
        }

        return $conflicts;
    }

    /**
     * Conflicts of an existing rental with all other rentals.
     *
     * @return MaterialConflict[]
     */
    public function findForRental(\App\Domain\Rental\Rental $rental): array
    {
        return $this->find($this->planned->plannedByRental($rental->id), $rental->occupiedFrom(), $rental->occupiedUntil(), [$rental->id]);
    }

    private function name(string $articleId): string
    {
        if (isset($this->nameCache[$articleId])) {
            return $this->nameCache[$articleId];
        }
        $article = $this->articles?->findById($articleId);
        $name = 'Artikel';
        if ($article !== null) {
            $manufacturer = $article->manufacturerId !== null ? $this->manufacturers?->findById($article->manufacturerId) : null;
            $name = ArticleDisplayName::of($article, $manufacturer);
        }

        return $this->nameCache[$articleId] = $name;
    }
}
