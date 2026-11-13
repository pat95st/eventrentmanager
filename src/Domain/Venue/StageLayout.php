<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/**
 * Eine konkrete Aufbaumöglichkeit einer mobilen Bühne: welche Bühnenelemente
 * wo liegen. Koordinaten und Maße in Rasterzellen von 0,5 m
 * (StageElementSize::CELL_M), Ursprung vorne links.
 */
final class StageLayout
{
    public const VARIANT_FEWEST = 'fewest';
    public const VARIANT_ONLY_2X1 = 'only_2x1';
    public const VARIANT_ONLY_1X1 = 'only_1x1';

    /**
     * @param list<array{size: StageElementSize, x: int, y: int, w: int, h: int}> $placements
     * @param string|null $variant eine der VARIANT_*-Konstanten; null bei Vorschlägen aus einem Elementbestand
     */
    public function __construct(
        public readonly int $widthCells,
        public readonly int $depthCells,
        public readonly array $placements,
        public readonly ?string $variant = null,
    ) {
    }

    public function widthM(): float
    {
        return $this->widthCells * StageElementSize::CELL_M;
    }

    public function depthM(): float
    {
        return $this->depthCells * StageElementSize::CELL_M;
    }

    public function areaSquareMeters(): float
    {
        return $this->widthM() * $this->depthM();
    }

    public function elementCount(): int
    {
        return count($this->placements);
    }

    /** @return array<string, int> Elementgröße (StageElementSize->value) => Anzahl, nur verwendete Größen, größte zuerst */
    public function counts(): array
    {
        $counts = [];
        foreach (StageElementSize::all() as $size) {
            $counts[$size->value] = 0;
        }
        foreach ($this->placements as $placement) {
            $counts[$placement['size']->value]++;
        }

        return array_filter($counts, static fn (int $count) => $count > 0);
    }
}
