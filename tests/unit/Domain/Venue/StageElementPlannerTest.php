<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Venue;

use App\Domain\Venue\StageElementPlanner;
use App\Domain\Venue\StageLayout;
use Codeception\Test\Unit;

final class StageElementPlannerTest extends Unit
{
    public function testEvenStageNeedsOnlyTwoByOneElementsOrOnlySquares(): void
    {
        $layouts = StageElementPlanner::forDimensions(6.0, 4.0);

        $this->assertSame([StageLayout::VARIANT_FEWEST, StageLayout::VARIANT_ONLY_1X1], array_map(static fn (StageLayout $l) => $l->variant, $layouts));
        $this->assertSame(['2x1' => 12], $layouts[0]->counts());
        $this->assertSame(['1x1' => 24], $layouts[1]->counts());
        foreach ($layouts as $layout) {
            $this->assertCoversExactly($layout);
        }
    }

    public function testOddStageNeedsOneSquareElement(): void
    {
        $layouts = StageElementPlanner::forDimensions(3.0, 3.0);

        $this->assertSame(['2x1' => 4, '1x1' => 1], $layouts[0]->counts());
        $this->assertCoversExactly($layouts[0]);
    }

    public function testHalfMetreStripUsesHalfDepthElements(): void
    {
        $layouts = StageElementPlanner::forDimensions(4.5, 3.0);

        $this->assertSame(1, count($layouts));
        $this->assertSame(['2x1' => 6, '2x0.5' => 1, '1x0.5' => 1], $layouts[0]->counts());
        $this->assertCoversExactly($layouts[0]);
    }

    public function testNoSuggestionOffGridOrWhenNotTileable(): void
    {
        $this->assertSame([], StageElementPlanner::forDimensions(7.3, 4.0));
        $this->assertSame([], StageElementPlanner::forDimensions(2.5, 2.5));
        $this->assertSame([], StageElementPlanner::forDimensions(41.0, 4.0));
    }

    public function testStockSuggestionsUseAtMostTheStockAndStartWithTheLargestArea(): void
    {
        $stock = ['2x1' => 12, '1x1' => 4];
        $layouts = StageElementPlanner::forStock($stock);

        $this->assertSame(['7x4', '14x2', '28x1', '9x3', '5x5'], array_map(
            static fn (StageLayout $l) => $l->widthM() . 'x' . $l->depthM(),
            $layouts
        ));
        foreach ($layouts as $layout) {
            $this->assertCoversExactly($layout);
            foreach ($layout->counts() as $size => $count) {
                $this->assertSame(true, $count <= $stock[$size]);
            }
        }
        $this->assertSame([], StageElementPlanner::leftover($stock, $layouts[0]));
        $this->assertSame(['1x1' => 3], StageElementPlanner::leftover($stock, $layouts[4]));
    }

    public function testStockOfTwoByOneOnlyCannotBuildOddDepth(): void
    {
        $layouts = StageElementPlanner::forStock(['2x1' => 5]);

        $this->assertSame(['5x2', '10x1'], array_map(static fn (StageLayout $l) => $l->widthM() . 'x' . $l->depthM(), $layouts));
    }

    public function testEmptyOrTooSmallStockYieldsNothing(): void
    {
        $this->assertSame([], StageElementPlanner::forStock([]));
        $this->assertSame([], StageElementPlanner::forStock(['2x1' => 0, 'unbekannt' => 9]));
        $this->assertSame([], StageElementPlanner::forStock(['1x0.5' => 1]));
    }

    /** Jede Rasterzelle ist von genau einem Element belegt, kein Element ragt über die Bühne hinaus. */
    private function assertCoversExactly(StageLayout $layout): void
    {
        $covered = array_fill(0, $layout->widthCells * $layout->depthCells, 0);
        foreach ($layout->placements as $placement) {
            $this->assertSame(true, $placement['x'] + $placement['w'] <= $layout->widthCells && $placement['y'] + $placement['h'] <= $layout->depthCells);
            $this->assertSame(
                [$placement['size']->longCells(), $placement['size']->shortCells()],
                [max($placement['w'], $placement['h']), min($placement['w'], $placement['h'])]
            );
            for ($y = $placement['y']; $y < $placement['y'] + $placement['h']; $y++) {
                for ($x = $placement['x']; $x < $placement['x'] + $placement['w']; $x++) {
                    $covered[$y * $layout->widthCells + $x]++;
                }
            }
        }

        $this->assertSame([1], array_values(array_unique($covered)));
    }
}
