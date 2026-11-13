<?php
/** @var list<array{date: \DateTimeImmutable, isToday: bool, events: list<array{kind: string, time: ?string, name: string, customer: string, location: string, href: string}>}> $schedule */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$weekdays = explode(',', t('dashboard.schedule.weekdays'));
?>
<section class="rounded-lg border border-slate-200 bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="lagerplan-titel">
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
