<?php
/**
 * Listenansicht der nächsten Veranstaltungen (Standard auf Mobilgeräten).
 *
 * @var array<string, \App\Application\Rental\Query\CalendarRentalEntry[]> $groups
 * @var \DateTimeImmutable $anchor
 */

use App\Infrastructure\Http\Controllers\CalendarController;

use function App\Infrastructure\Http\e;

$weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
$today = new DateTimeImmutable('today');
$dayLabel = static function (DateTimeImmutable $d) use ($today, $weekdays): string {
    $diff = (int) $today->diff($d)->format('%r%a');
    $prefix = match ($diff) { 0 => 'Heute · ', 1 => 'Morgen · ', default => '' };
    return $prefix . $weekdays[(int) $d->format('N') - 1] . ', ' . (int) $d->format('j') . '. ' . CalendarController::monthName($d) . ' ' . $d->format('Y');
};
$timeRange = static function ($r): string {
    $sameDay = $r->startDate->format('Y-m-d') === $r->endDate->format('Y-m-d');
    $days = $sameDay ? $r->startDate->format('d.m.') : $r->startDate->format('d.m.') . ' – ' . $r->endDate->format('d.m.');
    if ($r->pickupAt !== null && $r->returnAt !== null) {
        return $r->pickupAt->format('d.m. H:i') . ' – ' . ($r->pickupAt->format('Y-m-d') === $r->returnAt->format('Y-m-d') ? $r->returnAt->format('H:i') : $r->returnAt->format('d.m. H:i'));
    }
    return $days . ($sameDay ? ' · ganztägig' : '');
};
?>
<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($groups === []): ?>
    <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
        In den nächsten 60 Tagen ab <?= e($anchor->format('d.m.Y')) ?> sind keine Veranstaltungen geplant.
    </div>
<?php else: ?>
    <div class="space-y-5">
        <?php foreach ($groups as $key => $entries): ?>
            <?php $date = new DateTimeImmutable($key); ?>
            <section>
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide <?= $key === $today->format('Y-m-d') ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400' ?>"><?= e($dayLabel($date)) ?></h2>
                <ul class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                    <?php foreach ($entries as $entry): ?>
                        <?php $r = $entry->rental; $color = CalendarController::colorFor($r->id); ?>
                        <li>
                            <a href="/veranstaltungen/<?= e($r->id) ?>" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <span class="mt-1.5 shrink-0 rounded-full" style="width:.625rem; height:.625rem; background: <?= e($color) ?>"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e($r->eventName) ?></span>
                                    <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                        <?= e($entry->customer->displayName()) ?><?= $r->eventLocation ? ' · ' . e($r->eventLocation) : '' ?>
                                    </span>
                                    <?php if ($entry->materialLabels !== []): ?>
                                        <span class="mt-0.5 block truncate text-xs text-slate-400 dark:text-slate-500"><?= e(implode(', ', $entry->materialLabels)) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="shrink-0 text-right text-xs text-slate-500 dark:text-slate-400" style="white-space:nowrap"><?= e($timeRange($r)) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
