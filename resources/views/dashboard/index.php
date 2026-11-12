<?php
/** @var \App\Domain\Dashboard\DashboardWidget[] $widgets */
/** @var array<string, array{count: int, items: array<int, array{label: string, sub: string, href: ?string}>, href: string}> $sections */
/** @var \App\Application\Statistics\Query\StatisticChart[] $statistics */
/** @var list<array{date: \DateTimeImmutable, isToday: bool, events: list<array{kind: string, time: ?string, name: string, customer: string, location: string, href: string}>}> $schedule */

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
    <?php
    $hasSchedule = in_array(\App\Domain\Dashboard\DashboardWidget::UpcomingRentals, $widgets, true);
    $incomeWidget = in_array(\App\Domain\Dashboard\DashboardWidget::IncomeExpense, $widgets, true) ? \App\Domain\Dashboard\DashboardWidget::IncomeExpense : null;
    $listWidgets = array_values(array_filter(
        $widgets,
        fn ($widget) => $widget !== \App\Domain\Dashboard\DashboardWidget::UpcomingRentals && $widget !== \App\Domain\Dashboard\DashboardWidget::IncomeExpense,
    ));
    $weekdays = explode(',', t('dashboard.schedule.weekdays'));
    ?>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        <?php if ($hasSchedule): ?>
            <section class="rounded-lg border border-slate-200 bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900 lg:col-span-2" aria-labelledby="lagerplan-titel">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="lagerplan-titel" class="text-base font-semibold text-slate-900 dark:text-slate-100"><?= e(t('dashboard.schedule.title')) ?></h2>
                    <a href="/kalender" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400"><?= e(t('dashboard.schedule.open_calendar')) ?></a>
                </div>
                <ol class="mt-2">
                    <?php foreach ($schedule as $day): ?>
                        <li class="grid grid-cols-[4.5rem_minmax(0,1fr)] gap-4 border-t border-slate-100 py-3 dark:border-slate-800">
                            <div class="leading-tight">
                                <span class="block text-xs <?= $day['isToday'] ? 'font-semibold text-brand-700 dark:text-brand-400' : 'text-slate-500 dark:text-slate-400' ?>">
                                    <?= e($day['isToday'] ? t('dashboard.schedule.today') : ($weekdays[(int) $day['date']->format('w')] ?? '')) ?>
                                </span>
                                <span class="block text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-100"><?= e($day['date']->format('d.m.')) ?></span>
                            </div>
                            <?php if ($day['events'] === []): ?>
                                <p class="self-center text-sm text-slate-400 dark:text-slate-500"><?= e(t('dashboard.schedule.nothing_today')) ?></p>
                            <?php else: ?>
                                <ul class="space-y-2">
                                    <?php foreach ($day['events'] as $event): ?>
                                        <?php $isOut = $event['kind'] === 'out'; ?>
                                        <li>
                                            <a href="<?= e($event['href']) ?>" class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md px-3 py-2 <?= $isOut ? 'bg-brand-50 hover:bg-brand-100 dark:bg-brand-950/30 dark:hover:bg-brand-950/60' : 'bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:hover:bg-emerald-950/60' ?>">
                                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-white <?= $isOut ? 'bg-brand-600' : 'bg-emerald-600' ?>">
                                                    <?= e($isOut ? t('dashboard.schedule.pickup') : t('dashboard.schedule.return')) ?>
                                                </span>
                                                <span class="font-medium text-slate-900 dark:text-slate-100"><?= e($event['name']) ?></span>
                                                <span class="text-sm text-slate-500 dark:text-slate-400"><?= e($event['customer']) ?></span>
                                                <span class="ml-auto text-sm text-slate-600 dark:text-slate-300">
                                                    <?= e(implode(' · ', array_filter([$event['time'] !== null ? t('dashboard.schedule.time', ['time' => $event['time']]) : null, $event['location'] !== '' ? $event['location'] : null]))) ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (count($schedule) === 1 && $schedule[0]['events'] === []): ?>
                    <p class="border-t border-slate-100 pt-3 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400"><?= e(t('dashboard.schedule.empty')) ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($listWidgets !== [] || $incomeWidget !== null): ?>
            <section class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900 <?= $hasSchedule ? '' : 'lg:col-span-3' ?>">
                <?php foreach ($listWidgets as $widget): ?>
                    <?php $section = $sections[$widget->value]; ?>
                    <div class="px-5 py-3">
                        <a href="<?= e($section['href']) ?>" class="group flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-slate-700 group-hover:text-brand-700 dark:text-slate-300 dark:group-hover:text-brand-400"><?= e($widget->label()) ?></span>
                            <span class="font-semibold tabular-nums <?= $section['count'] === 0 ? 'text-slate-300 dark:text-slate-600' : ($widget === \App\Domain\Dashboard\DashboardWidget::OverdueInvoices ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-slate-100') ?>"><?= e((string) $section['count']) ?></span>
                        </a>
                        <?php if ($section['items'] !== []): ?>
                            <ul class="mt-1.5 space-y-1">
                                <?php foreach (array_slice($section['items'], 0, 3) as $item): ?>
                                    <li class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        <?php if ($item['href'] !== null): ?>
                                            <a href="<?= e($item['href']) ?>" class="font-medium text-slate-600 hover:text-brand-600 dark:text-slate-300 dark:hover:text-brand-400"><?= e($item['label']) ?></a>
                                        <?php else: ?>
                                            <span class="font-medium text-slate-600 dark:text-slate-300"><?= e($item['label']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($item['sub'] !== ''): ?> <span><?= e($item['sub']) ?></span><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($section['count'] > 3): ?>
                                <a href="<?= e($section['href']) ?>" class="mt-1 block text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                                    <?= e(t('dashboard.more_count', ['count' => (string) ($section['count'] - 3)])) ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($incomeWidget !== null): ?>
                    <?php $income = $sections[$incomeWidget->value]['items'][0] ?? null; ?>
                    <a href="<?= e($sections[$incomeWidget->value]['href']) ?>" class="block px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($incomeWidget->label()) ?></span>
                        <?php if ($income !== null): ?>
                            <span class="mt-0.5 block text-base font-semibold text-slate-900 dark:text-slate-100"><?= e($income['label']) ?></span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($income['sub']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (($statistics ?? []) !== []): ?>
    <section class="mt-8" aria-labelledby="statistiken-titel">
        <h2 id="statistiken-titel" class="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">Statistiken</h2>
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
