<?php
/** @var \App\Domain\Dashboard\DashboardWidget[] $widgets */
/** @var array<string, array{count: int, items: array<int, array{label: string, sub: string, href: ?string}>, href: string}> $sections */
/** @var \App\Application\Statistics\Query\StatisticChart[] $statistics */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-2">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('dashboard.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('dashboard.subtitle')) ?></p>
    </div>
    <a href="/profil/dashboard" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
        <?= e(t('dashboard.configure')) ?>
    </a>
</div>

<?php if ($widgets === []): ?>
    <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('dashboard.empty')) ?></p>
        <a href="/profil/dashboard" class="mt-3 inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('dashboard.configure')) ?>
        </a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($widgets as $widget): ?>
            <?php $section = $sections[$widget->value]; ?>
            <div class="flex flex-col rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-2 flex items-start justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e($widget->label()) ?></h2>
                    <?php if ($section['count'] > 0): ?>
                        <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-brand-100 px-1.5 py-0.5 text-xs font-semibold text-brand-700 dark:bg-brand-950/40 dark:text-brand-400">
                            <?= e((string) $section['count']) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($section['items'] === []): ?>
                    <p class="flex-1 text-sm text-slate-400 dark:text-slate-500"><?= e(t('dashboard.widget_empty')) ?></p>
                <?php else: ?>
                    <ul class="flex-1 space-y-2">
                        <?php foreach ($section['items'] as $item): ?>
                            <li class="border-b border-slate-100 pb-2 last:border-0 last:pb-0 dark:border-slate-800">
                                <?php if ($item['href'] !== null): ?>
                                    <a href="<?= e($item['href']) ?>" class="block text-sm font-medium text-slate-700 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-400"><?= e($item['label']) ?></a>
                                <?php else: ?>
                                    <span class="block text-sm font-medium text-slate-700 dark:text-slate-300"><?= e($item['label']) ?></span>
                                <?php endif; ?>
                                <?php if ($item['sub'] !== ''): ?>
                                    <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e($item['sub']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($section['count'] > count($section['items'])): ?>
                        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                            <?= e(t('dashboard.more_count', ['count' => (string) ($section['count'] - count($section['items'])) ])) ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>

                <a href="<?= e($section['href']) ?>" class="mt-3 block text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                    <?= e(t('dashboard.view_all')) ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (($statistics ?? []) !== []): ?>
    <section class="mt-8" aria-labelledby="statistiken-titel">
        <h2 id="statistiken-titel" class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Statistiken</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($statistics as $chart): ?>
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
            <?php endforeach; ?>
        </div>
    </section>
    <style>
        .stat-card:hover, .stat-card:focus-visible { border-color: rgb(var(--color-brand-300, 148 163 184)); box-shadow: 0 4px 12px -4px rgba(15, 23, 42, .15); outline: none; }
        .stat-card:hover .stat-card-more, .stat-card:focus-visible .stat-card-more { text-decoration: underline; }
    </style>
    <script src="/assets/js/dashboard-charts.js" defer></script>
<?php endif; ?>
