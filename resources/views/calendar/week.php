<?php
/**
 * Wochenansicht: Tage nebeneinander, Stunden von oben nach unten.
 *
 * @var array<string, array{date: \DateTimeImmutable, allDay: \App\Application\Rental\Query\CalendarRentalEntry[], timed: list<array>, columns: int}> $days
 */

use App\Infrastructure\Http\Controllers\CalendarController;

use function App\Infrastructure\Http\e;

$hourHeight = 44; // px pro Stunde
$weekdayShort = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$now = new DateTimeImmutable();
$hasAllDay = array_filter($days, static fn ($d) => $d['allDay'] !== []) !== [];
$tooltip = static fn ($entry): string => $entry->rental->eventName . ' — ' . $entry->customer->displayName()
    . ($entry->rental->eventLocation ? ' — ' . $entry->rental->eventLocation : '')
    . ($entry->materialLabels !== [] ? ' — ' . implode(', ', $entry->materialLabels) : '');
// Anfangs bis 7 Uhr scrollen bzw. früher, wenn ein Termin früher beginnt.
$scrollHour = 7;
foreach ($days as $d) {
    foreach ($d['timed'] as $item) {
        if (!$item['continuesBefore']) {
            $scrollHour = min($scrollHour, intdiv($item['start'], 60));
        }
    }
}
$fmt = static fn (int $min): string => sprintf('%02d:%02d', intdiv(min($min, 1439), 60), min($min, 1439) % 60);
?>
<?php include __DIR__ . '/_nav.php'; ?>
<style>
    .wk-wrap { border:1px solid rgb(226 232 240); border-radius:.5rem; overflow:auto; background:#fff; }
    .dark .wk-wrap { border-color:rgb(30 41 59); background:rgb(15 23 42); }
    .wk-grid { display:grid; grid-template-columns:3.25rem repeat(7, minmax(5.5rem, 1fr)); min-width:640px; }
    .wk-head { position:sticky; top:0; z-index:3; background:inherit; }
    .wk-cell-h { padding:.5rem .25rem; text-align:center; border-left:1px solid rgb(226 232 240); border-bottom:1px solid rgb(226 232 240); background:rgb(248 250 252); }
    .dark .wk-cell-h { border-color:rgb(30 41 59); background:rgb(15 23 42); }
    .wk-allday { border-left:1px solid rgb(226 232 240); border-bottom:1px solid rgb(226 232 240); padding:.25rem; display:flex; flex-direction:column; gap:.125rem; min-height:1.75rem; }
    .dark .wk-allday { border-color:rgb(30 41 59); }
    .wk-col { position:relative; border-left:1px solid rgb(226 232 240); background-image:repeating-linear-gradient(to bottom, rgb(226 232 240) 0, rgb(226 232 240) 1px, transparent 1px, transparent <?= $hourHeight ?>px); }
    .dark .wk-col { border-color:rgb(30 41 59); background-image:repeating-linear-gradient(to bottom, rgb(30 41 59) 0, rgb(30 41 59) 1px, transparent 1px, transparent <?= $hourHeight ?>px); }
    .wk-col.is-weekend { background-color:rgb(248 250 252 / .6); }
    .dark .wk-col.is-weekend { background-color:rgb(2 6 23 / .4); }
    .wk-hours { position:relative; }
    .wk-hours span { position:absolute; right:.375rem; transform:translateY(-50%); font-size:.6875rem; color:rgb(148 163 184); }
    .wk-ev { position:absolute; overflow:hidden; border-radius:.375rem; padding:.125rem .375rem; font-size:.75rem; line-height:1.15; color:var(--c);
             background:color-mix(in srgb, var(--c) 14%, white); box-shadow:inset 3px 0 0 var(--c); border:1px solid color-mix(in srgb, var(--c) 30%, transparent); }
    .wk-ev:hover { background:color-mix(in srgb, var(--c) 24%, white); z-index:2; }
    .dark .wk-ev { background:color-mix(in srgb, var(--c) 28%, rgb(15 23 42)); color:color-mix(in srgb, var(--c) 40%, white); }
    .wk-ev.cont-before { border-top-left-radius:0; border-top-right-radius:0; border-top-style:dashed; }
    .wk-ev.cont-after { border-bottom-left-radius:0; border-bottom-right-radius:0; border-bottom-style:dashed; }
    .wk-chip { display:block; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; border-radius:.25rem; padding:.0625rem .375rem; font-size:.6875rem; font-weight:500; color:#fff; background:var(--c); }
    .wk-now { position:absolute; left:0; right:0; height:2px; background:#ef4444; z-index:2; }
    .wk-now::before { content:""; position:absolute; left:-4px; top:-3px; width:8px; height:8px; border-radius:9999px; background:#ef4444; }
    .wk-today-num { display:inline-flex; width:1.75rem; height:1.75rem; align-items:center; justify-content:center; border-radius:9999px; background:#ef4444; color:#fff; }
</style>

<div class="wk-wrap" id="week-scroll" style="max-height: 75vh;">
    <div class="wk-grid wk-head">
        <div class="wk-cell-h" style="border-left:0"></div>
        <?php $i = 0; foreach ($days as $key => $d): ?>
            <a href="/kalender?ansicht=monat&amp;datum=<?= e($key) ?>" class="wk-cell-h" title="Monat anzeigen">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= e($weekdayShort[$i++]) ?></div>
                <div class="mt-0.5 text-base font-semibold text-slate-800 dark:text-slate-100">
                    <?php if ($key === $today): ?><span class="wk-today-num"><?= (int) $d['date']->format('j') ?></span><?php else: ?><?= (int) $d['date']->format('j') ?><?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if ($hasAllDay): ?>
            <div class="wk-cell-h" style="border-left:0; font-size:.625rem; color:rgb(148 163 184); padding-top:.375rem">ganz-<br>tägig</div>
            <?php foreach ($days as $d): ?>
                <div class="wk-allday" style="background:inherit">
                    <?php foreach ($d['allDay'] as $entry): ?>
                        <a class="wk-chip" style="--c: <?= e(CalendarController::colorFor($entry->rental->id)) ?>" href="/veranstaltungen/<?= e($entry->rental->id) ?>" title="<?= e($tooltip($entry)) ?>"><?= e($entry->rental->eventName) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="wk-grid" style="height: <?= 24 * $hourHeight ?>px;">
        <div class="wk-hours">
            <?php for ($h = 1; $h < 24; $h++): ?>
                <span style="top: <?= $h * $hourHeight ?>px"><?= sprintf('%02d:00', $h) ?></span>
            <?php endfor; ?>
        </div>
        <?php $i = 0; foreach ($days as $key => $d): ?>
            <div class="wk-col <?= $i++ >= 5 ? 'is-weekend' : '' ?>">
                <?php foreach ($d['timed'] as $item): ?>
                    <?php
                    $entry = $item['entry'];
                    $top = $item['start'] / 60 * $hourHeight;
                    $height = max(($item['end'] - $item['start']) / 60 * $hourHeight - 2, 18);
                    $width = 100 / $d['columns'];
                    $left = $item['column'] * $width;
                    $classes = ($item['continuesBefore'] ? ' cont-before' : '') . ($item['continuesAfter'] ? ' cont-after' : '');
                    ?>
                    <a href="/veranstaltungen/<?= e($entry->rental->id) ?>" class="wk-ev<?= $classes ?>"
                       style="--c: <?= e(CalendarController::colorFor($entry->rental->id)) ?>; top: <?= round($top, 1) ?>px; height: <?= round($height, 1) ?>px; left: calc(<?= round($left, 3) ?>% + 2px); width: calc(<?= round($width, 3) ?>% - 4px);"
                       title="<?= e($tooltip($entry)) ?>">
                        <span class="block font-semibold" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap"><?= e($entry->rental->eventName) ?></span>
                        <?php if ($height >= 32): ?>
                            <span class="block" style="opacity:.8"><?= e($fmt($item['start'])) ?>–<?= e($item['end'] >= 1440 ? '24:00' : $fmt($item['end'])) ?></span>
                        <?php endif; ?>
                        <?php if ($height >= 50): ?>
                            <span class="block" style="opacity:.8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap"><?= e($entry->customer->displayName()) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php if ($key === $today): ?>
                    <div class="wk-now" style="top: <?= round(((int) $now->format('G') * 60 + (int) $now->format('i')) / 60 * $hourHeight, 1) ?>px"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
    Zeiten aus Abholung und Rückgabe der Veranstaltung; ohne Zeitangaben erscheint die Veranstaltung als ganztägig.
</p>
<script>
    (function () {
        var box = document.getElementById('week-scroll');
        if (box) { box.scrollTop = <?= $scrollHour * $hourHeight ?>; }
    })();
</script>
