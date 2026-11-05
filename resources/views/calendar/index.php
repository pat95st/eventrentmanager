<?php
/** @var \DateTimeImmutable[][] $weeks */
/** @var array<string, \App\Application\Rental\Query\CalendarRentalEntry[]> $entriesByDay */
/** @var \DateTimeImmutable $currentMonth */
/** @var string $view */
/** @var string $label */

use function App\Infrastructure\Http\e;

$weekdayLabels = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$maxVisiblePerDay = 3;
?>

<?php include __DIR__ . '/_nav.php'; ?>
<style>
    .cal-chip { background: color-mix(in srgb, var(--c) 12%, transparent); color: var(--c); box-shadow: inset 3px 0 0 var(--c); }
    .cal-chip:hover { background: color-mix(in srgb, var(--c) 22%, transparent); }
    .dark .cal-chip { background: color-mix(in srgb, var(--c) 25%, transparent); color: color-mix(in srgb, var(--c) 45%, white); }
</style>

<!--
    Bewusst als echtes <table>-Element statt als CSS-Grid umgesetzt: eine
    Tabelle mit table-fixed sorgt browserseitig garantiert für 7 gleich
    breite Spalten und sieht dadurch sofort wie ein "normaler" Monatskalender
    aus, unabhängig von der (in dieser Sandbox händisch statt per echtem
    Tailwind-Build erzeugten) Utility-CSS-Datei.
-->
<div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
<table class="w-full table-fixed" data-no-sort>
    <thead>
        <tr class="bg-slate-50 dark:bg-slate-900">
            <?php foreach ($weekdayLabels as $i => $label): ?>
                <th class="border border-slate-200 px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400 <?= $i >= 5 ? 'bg-slate-100 dark:bg-slate-800' : '' ?>">
                    <span class="hidden sm:inline"><?= e($label) ?></span>
                    <span class="sm:hidden"><?= e(mb_substr($label, 0, 2)) ?></span>
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($weeks as $week): ?>
            <tr>
                <?php foreach ($week as $i => $day): ?>
                    <?php
                    $key = $day->format('Y-m-d');
                    $inCurrentMonth = $day->format('Y-m') === $currentMonth->format('Y-m');
                    $isToday = $key === $today;
                    $isWeekend = $i >= 5;
                    $dayEntries = $entriesByDay[$key] ?? [];
                    $visibleEntries = array_slice($dayEntries, 0, $maxVisiblePerDay);
                    $hiddenCount = count($dayEntries) - count($visibleEntries);
                    ?>
                    <td class="h-28 w-[14.28%] align-top border border-slate-200 p-2 dark:border-slate-800 <?= $isWeekend ? 'bg-slate-50/60 dark:bg-slate-950/40' : 'bg-white dark:bg-slate-900' ?> <?= $inCurrentMonth ? '' : 'opacity-50' ?>">
                        <div class="mb-1 flex items-center justify-start">
                            <a href="/kalender?ansicht=woche&amp;datum=<?= e($key) ?>" title="Woche anzeigen" class="flex h-6 w-6 items-center justify-center rounded-full text-xs <?= $isToday ? 'bg-brand-600 font-semibold text-white' : 'text-slate-500 dark:text-slate-400' ?>">
                                <?= (int) $day->format('j') ?>
                            </a>
                        </div>
                        <div class="space-y-1">
                            <?php foreach ($visibleEntries as $entry): ?>
                                <a
                                    href="/veranstaltungen/<?= e($entry->rental->id) ?>"
                                    class="cal-chip block truncate rounded px-1.5 py-1 text-xs font-medium"
                                    style="--c: <?= e(\App\Infrastructure\Http\Controllers\CalendarController::colorFor($entry->rental->id)) ?>"
                                    title="<?= e($entry->rental->eventName . ' — ' . $entry->customer->displayName() . ($entry->materialLabels !== [] ? ' — ' . implode(', ', $entry->materialLabels) : '')) ?>"
                                >
                                    <?= e($entry->rental->eventName) ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if ($hiddenCount > 0): ?>
                                <span class="block px-1.5 text-xs font-medium text-slate-400 dark:text-slate-500">+ <?= $hiddenCount ?> weitere</span>
                            <?php endif; ?>
                        </div>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
    Angezeigt wird das tatsächlich ausgegebene Material anhand der Inventarnummer; solange noch nichts ausgegeben wurde, werden die geplanten Artikel angezeigt.
</p>
