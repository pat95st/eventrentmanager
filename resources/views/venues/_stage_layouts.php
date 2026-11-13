<?php
/**
 * Aufbaumöglichkeiten einer mobilen Bühne aus Bühnenelementen (siehe
 * App\Domain\Venue\StageElementPlanner): je Vorschlag eine maßstäbliche
 * Draufsicht, in der jedes Rechteck ein Element ist, plus Stückliste.
 *
 * @var \App\Domain\Venue\Stage $stage mobile Bühne
 * @var \Closure(float): string $formatMeters
 */

use App\Domain\Venue\StageElementPlanner;
use App\Domain\Venue\StageElementSize;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$layouts = $stage->buildSuggestions();
$fromStock = $stage->isDefinedByElementStock();
/** @param array<string, int> $counts */
$partsList = static fn (array $counts): string => implode(', ', array_map(
    static fn (string $value, int $count): string => $count . ' × ' . StageElementSize::from($value)->label(),
    array_keys($counts),
    $counts
));
?>
<div class="mt-2">
    <p class="text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('venues.stages.layouts.title')) ?></p>
    <?php if ($layouts === []): ?>
        <p class="mt-1 text-xs text-amber-700 dark:text-amber-400"><?= e(t($fromStock ? 'venues.stages.layouts.none_for_stock' : 'venues.stages.layouts.none_for_dimensions')) ?></p>
    <?php else: ?>
        <ul class="mt-1 flex flex-wrap gap-2">
            <?php foreach ($layouts as $layout): ?>
                <?php
                    // Maßstab: passt in 168 × 96 px, höchstens 12 px je 0,5 m.
                    $scale = min(12, 168 / $layout->widthCells, 96 / $layout->depthCells);
                    $svgWidth = round($layout->widthCells * $scale, 1);
                    $svgHeight = round($layout->depthCells * $scale, 1);
                    $dimensions = $formatMeters($layout->widthM()) . ' × ' . $formatMeters($layout->depthM()) . ' m';
                    $leftover = $fromStock ? StageElementPlanner::leftover($stage->elementStock, $layout) : [];
                ?>
                <li class="w-48 rounded-md border border-slate-200 p-2 dark:border-slate-700">
                    <p class="text-xs font-medium text-slate-800 dark:text-slate-200">
                        <?php if ($fromStock): ?>
                            <?= e($dimensions) ?> <span class="font-normal text-slate-500 dark:text-slate-400">(<?= e($formatMeters($layout->areaSquareMeters())) ?> m²)</span>
                        <?php else: ?>
                            <?= e(t('venues.stages.layouts.variant.' . $layout->variant)) ?>
                        <?php endif; ?>
                    </p>
                    <div class="mt-1 flex h-24 items-center justify-center">
                        <svg viewBox="0 0 <?= $layout->widthCells ?> <?= $layout->depthCells ?>" width="<?= $svgWidth ?>" height="<?= $svgHeight ?>"
                             class="text-brand-600 dark:text-brand-400" role="img"
                             aria-label="<?= e(t('venues.stages.layouts.sketch_label', ['dimensions' => $dimensions, 'count' => (string) $layout->elementCount()])) ?>">
                            <?php foreach ($layout->placements as $placement): ?>
                                <rect x="<?= $placement['x'] ?>" y="<?= $placement['y'] ?>" width="<?= $placement['w'] ?>" height="<?= $placement['h'] ?>"
                                      fill="currentColor" fill-opacity="0.14" stroke="currentColor" stroke-width="1" vector-effect="non-scaling-stroke"/>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400"><?= e($partsList($layout->counts())) ?></p>
                    <?php if ($leftover !== []): ?>
                        <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('venues.stages.layouts.leftover', ['parts' => $partsList($leftover)])) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
