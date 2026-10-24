<?php
/** @var \DateTimeImmutable[][] $weeks */
/** @var array<string, \App\Application\Rental\Query\CalendarRentalEntry[]> $entriesByDay */
/** @var \DateTimeImmutable $currentMonth */
/** @var string $prevMonth */
/** @var string $nextMonth */

use function App\Infrastructure\Http\e;

$weekdayLabels = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

// Eigene deutsche Monatsnamen statt der intl-Erweiterung, damit die Seite
// ohne zusätzliche PHP-Extensions auskommt (siehe README: nur Kernmodule).
$germanMonths = [
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
    7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
];
$monthLabel = $germanMonths[(int) $currentMonth->format('n')] . ' ' . $currentMonth->format('Y');

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$maxVisiblePerDay = 3;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Kalender</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Welches Material ist wann unterwegs? Klicke auf eine Veranstaltung für Details.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="/kalender?monat=<?= e($prevMonth) ?>" aria-label="Vorheriger Monat"
           class="rounded-md border border-slate-300 p-2 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 010 1.06L8.06 11l4.73 4.71a.75.75 0 11-1.06 1.06l-5.25-5.25a.75.75 0 010-1.06l5.25-5.25a.75.75 0 011.06 0z" clip-rule="evenodd"/></svg>
        </a>
        <span class="min-w-[9rem] text-center text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e($monthLabel) ?></span>
        <a href="/kalender?monat=<?= e($nextMonth) ?>" aria-label="Nächster Monat"
           class="rounded-md border border-slate-300 p-2 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 010-1.06L11.94 9 7.21 4.29a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06 0z" clip-rule="evenodd"/></svg>
        </a>
    </div>
</div>

<!--
    Bewusst als echtes <table>-Element statt als CSS-Grid umgesetzt: eine
    Tabelle mit table-fixed sorgt browserseitig garantiert für 7 gleich
    breite Spalten und sieht dadurch sofort wie ein "normaler" Monatskalender
    aus, unabhängig von der (in dieser Sandbox händisch statt per echtem
    Tailwind-Build erzeugten) Utility-CSS-Datei.
-->
<div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
<table class="w-full table-fixed">
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
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs <?= $isToday ? 'bg-brand-600 font-semibold text-white' : 'text-slate-500 dark:text-slate-400' ?>">
                                <?= (int) $day->format('j') ?>
                            </span>
                        </div>
                        <div class="space-y-1">
                            <?php foreach ($visibleEntries as $entry): ?>
                                <a
                                    href="/veranstaltungen/<?= e($entry->rental->id) ?>"
                                    class="block truncate rounded bg-brand-50 px-1.5 py-1 text-xs font-medium text-brand-800 ring-1 ring-inset ring-brand-200 hover:bg-brand-100 dark:bg-brand-950/40 dark:text-brand-300 dark:ring-brand-800"
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
