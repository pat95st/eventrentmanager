<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/**
 * Schlägt Aufbaumöglichkeiten für mobile Bühnen aus Bühnenelementen vor —
 * in beide Richtungen:
 *
 *   - forDimensions(): Die Bühnenmaße sind vorgegeben; gesucht ist, wie
 *     viele Elemente welcher Größe dafür nötig sind (mehrere Varianten).
 *   - forStock(): Der Elementbestand ist vorgegeben; gesucht sind die
 *     rechteckigen Bühnen, die sich daraus bauen lassen.
 *
 * Gerechnet wird lückenlos im 0,5-m-Raster. Jede Lösung ist eine echte
 * Belegung (StageLayout mit Position jedes Elements), nicht nur eine
 * Flächenrechnung — eine Bühne von 1 × 3 m lässt sich z. B. nicht aus
 * 2 × 1-m-Elementen allein legen, obwohl die Fläche es zuließe.
 */
final class StageElementPlanner
{
    /** Größte planbare Kantenlänge in Metern; darüber wird nichts vorgeschlagen. */
    public const MAX_SIDE_M = 40.0;

    /** Höchstzahl der Vorschläge aus einem Elementbestand. */
    public const MAX_STOCK_SUGGESTIONS = 6;

    /** Suchschritte je Belegungsversuch, bevor er als "nicht baubar" gilt. */
    private const NODE_BUDGET = 4000;

    /** So viele schmalere Breiten werden je Tiefe probiert, bevor die Tiefe aufgegeben wird. */
    private const WIDTH_TRIES_PER_DEPTH = 8;

    /** @var list<bool> */
    private array $grid = [];
    private int $width = 0;
    private int $depth = 0;
    /** @var array<string, int> */
    private array $remaining = [];
    /** @var list<array{size: StageElementSize, x: int, y: int, w: int, h: int}> */
    private array $placements = [];
    /** @var list<array{size: StageElementSize, w: int, h: int}> */
    private array $options = [];
    private int $nodes = 0;

    /**
     * Varianten für eine Bühne mit vorgegebenen Maßen. Leeres Array, wenn
     * die Maße nicht im 0,5-m-Raster liegen, zu groß sind oder sich mit den
     * Standardgrößen nicht lückenlos legen lassen (z. B. 2,5 × 2,5 m).
     *
     * @return list<StageLayout> ohne Varianten mit identischem Elementbedarf
     */
    public static function forDimensions(float $widthM, float $depthM): array
    {
        $width = self::toCells($widthM);
        $depth = self::toCells($depthM);
        if ($width === null || $depth === null) {
            return [];
        }

        $unlimited = PHP_INT_MAX;
        $variants = [
            StageLayout::VARIANT_FEWEST => array_fill_keys(array_map(static fn (StageElementSize $s) => $s->value, StageElementSize::all()), $unlimited),
            StageLayout::VARIANT_ONLY_2X1 => [StageElementSize::TwoByOne->value => $unlimited],
            StageLayout::VARIANT_ONLY_1X1 => [StageElementSize::OneByOne->value => $unlimited],
        ];

        $layouts = [];
        $seen = [];
        foreach ($variants as $variant => $stock) {
            $placements = (new self())->tile($width, $depth, $stock);
            if ($placements === null) {
                continue;
            }
            $layout = new StageLayout($width, $depth, $placements, $variant);
            $signature = json_encode($layout->counts());
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $layouts[] = $layout;
        }

        return $layouts;
    }

    /**
     * Rechteckige Bühnen, die sich aus dem Bestand bauen lassen: je
     * Bühnentiefe die breiteste baubare Bühne, größte Fläche zuerst.
     *
     * @param array<string, int> $stock Elementgröße (StageElementSize->value) => vorhandene Anzahl
     * @return list<StageLayout>
     */
    public static function forStock(array $stock): array
    {
        $stock = self::normalizeStock($stock);
        $totalCells = 0;
        $hasHalfDepthElements = false;
        foreach ($stock as $value => $quantity) {
            $size = StageElementSize::from($value);
            $totalCells += $size->areaCells() * $quantity;
            $hasHalfDepthElements = $hasHalfDepthElements || $size->shortCells() === 1;
        }
        if ($totalCells === 0) {
            return [];
        }

        // Ohne 0,5 m tiefe Elemente kommen nur ganze Meter in Frage.
        $step = $hasHalfDepthElements ? 1 : 2;
        $maxSide = (int) (self::MAX_SIDE_M / StageElementSize::CELL_M);

        $layouts = [];
        for ($depth = 2; $depth * $depth <= $totalCells && $depth <= $maxSide; $depth += $step) {
            $width = min($maxSide, intdiv($totalCells, $depth));
            $width -= $width % $step;
            for ($try = 0; $try < self::WIDTH_TRIES_PER_DEPTH && $width >= $depth; $try++, $width -= $step) {
                $placements = (new self())->tile($width, $depth, $stock);
                if ($placements !== null) {
                    $layouts[] = new StageLayout($width, $depth, $placements);

                    break;
                }
            }
        }

        usort($layouts, static fn (StageLayout $a, StageLayout $b): int => [$b->widthCells * $b->depthCells, $b->depthCells] <=> [$a->widthCells * $a->depthCells, $a->depthCells]);

        return array_slice($layouts, 0, self::MAX_STOCK_SUGGESTIONS);
    }

    /**
     * Was vom Bestand übrig bleibt, wenn der Aufbau gestellt wird.
     *
     * @param array<string, int> $stock
     * @return array<string, int> nur Größen mit Rest > 0
     */
    public static function leftover(array $stock, StageLayout $layout): array
    {
        $used = $layout->counts();
        $leftover = [];
        foreach (self::normalizeStock($stock) as $value => $quantity) {
            $rest = $quantity - ($used[$value] ?? 0);
            if ($rest > 0) {
                $leftover[$value] = $rest;
            }
        }

        return $leftover;
    }

    /** Meter → Rasterzellen; null außerhalb des 0,5-m-Rasters oder der planbaren Größe. */
    private static function toCells(float $meters): ?int
    {
        $cells = $meters / StageElementSize::CELL_M;
        if ($meters <= 0.0 || $meters > self::MAX_SIDE_M || abs($cells - round($cells)) > 0.0001) {
            return null;
        }

        return (int) round($cells);
    }

    /**
     * @param array<string, int> $stock
     * @return array<string, int> bekannte Größen mit Anzahl > 0, größte zuerst
     */
    private static function normalizeStock(array $stock): array
    {
        $normalized = [];
        foreach (StageElementSize::all() as $size) {
            $quantity = (int) ($stock[$size->value] ?? 0);
            if ($quantity > 0) {
                $normalized[$size->value] = $quantity;
            }
        }

        return $normalized;
    }

    /**
     * Belegt ein Rechteck lückenlos mit Elementen aus $stock (große zuerst,
     * mit Zurücksetzen, falls der Bestand so nicht aufgeht).
     *
     * @param array<string, int> $stock
     * @return list<array{size: StageElementSize, x: int, y: int, w: int, h: int}>|null null = keine Belegung gefunden
     */
    private function tile(int $width, int $depth, array $stock): ?array
    {
        $this->width = $width;
        $this->depth = $depth;
        $this->grid = array_fill(0, $width * $depth, false);
        $this->remaining = $stock;
        $this->placements = [];
        $this->nodes = 0;

        $this->options = [];
        $availableCells = 0;
        foreach (StageElementSize::all() as $size) {
            $quantity = $stock[$size->value] ?? 0;
            if ($quantity <= 0) {
                continue;
            }
            $availableCells = $quantity === PHP_INT_MAX ? PHP_INT_MAX : min(PHP_INT_MAX, $availableCells + $quantity * $size->areaCells());
            // Quer (lange Seite entlang der Bühnenbreite) vor hochkant.
            $this->options[] = ['size' => $size, 'w' => $size->longCells(), 'h' => $size->shortCells()];
            $this->options[] = ['size' => $size, 'w' => $size->shortCells(), 'h' => $size->longCells()];
        }
        if ($availableCells < $width * $depth) {
            return null;
        }

        return $this->fill(0) ? $this->placements : null;
    }

    private function fill(int $from): bool
    {
        $cellCount = $this->width * $this->depth;
        while ($from < $cellCount && $this->grid[$from]) {
            $from++;
        }
        if ($from === $cellCount) {
            return true;
        }
        if (++$this->nodes > self::NODE_BUDGET + $cellCount) {
            return false;
        }

        $x = $from % $this->width;
        $y = intdiv($from, $this->width);

        foreach ($this->options as $option) {
            $value = $option['size']->value;
            if ($this->remaining[$value] <= 0 || !$this->fits($x, $y, $option['w'], $option['h'])) {
                continue;
            }

            $this->mark($x, $y, $option['w'], $option['h'], true);
            $this->remaining[$value]--;
            $this->placements[] = ['size' => $option['size'], 'x' => $x, 'y' => $y, 'w' => $option['w'], 'h' => $option['h']];

            if ($this->fill($from + $option['w'])) {
                return true;
            }

            array_pop($this->placements);
            $this->remaining[$value]++;
            $this->mark($x, $y, $option['w'], $option['h'], false);

            if ($this->nodes > self::NODE_BUDGET + $cellCount) {
                return false;
            }
        }

        return false;
    }

    private function fits(int $x, int $y, int $w, int $h): bool
    {
        if ($x + $w > $this->width || $y + $h > $this->depth) {
            return false;
        }
        // Die Zeile über $y ist bereits voll und die Zelle ($x,$y) frei: es
        // genügt, die oberste Zeile des Elements zu prüfen — alles darunter
        // kann nur von einem Element belegt sein, das bereits in Zeile $y ragt.
        for ($i = 0; $i < $w; $i++) {
            if ($this->grid[$y * $this->width + $x + $i]) {
                return false;
            }
        }

        return true;
    }

    private function mark(int $x, int $y, int $w, int $h, bool $occupied): void
    {
        for ($row = $y; $row < $y + $h; $row++) {
            $offset = $row * $this->width + $x;
            for ($i = 0; $i < $w; $i++) {
                $this->grid[$offset + $i] = $occupied;
            }
        }
    }
}
