<?php
/** @var \App\Application\Statistics\Query\StatisticChart $chart */

use function App\Infrastructure\Http\e;
?>
<a href="/statistiken/<?= e($chart->key->value) ?>" class="stat-card flex min-w-0 flex-col rounded-lg border border-slate-200 bg-white p-4 transition dark:border-slate-800 dark:bg-slate-900" data-stat="<?= e($chart->key->value) ?>">
    <div class="flex items-start justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e($chart->key->label()) ?></h3>
        <span class="stat-card-more whitespace-nowrap text-xs font-medium text-brand-600 dark:text-brand-400">Details →</span>
    </div>
    <p class="mt-1 truncate font-bold text-slate-900 dark:text-slate-100" style="font-size:1.375rem;line-height:1.75rem" title="<?= e($chart->headline) ?>"><?= e($chart->headline) ?></p>
    <p class="text-xs text-slate-500 dark:text-slate-400"><?= e($chart->headlineCaption) ?></p>
    <?php if ($chart->isEmpty()): ?>
        <div class="mt-3 flex flex-1 items-center justify-center rounded-md border border-dashed border-slate-200 text-sm text-slate-400 dark:border-slate-700 dark:text-slate-500" style="min-height:180px">Noch keine Daten</div>
    <?php else: ?>
        <div class="relative mt-3" style="height:180px">
            <canvas data-chart="<?= e($chart->toChartJson()) ?>" data-chart-compact="1" role="img" aria-label="<?= e($chart->key->label()) ?>"></canvas>
        </div>
    <?php endif; ?>
</a>
