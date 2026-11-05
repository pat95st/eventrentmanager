<?php
/**
 * Jahresansicht im Stil des iOS-Kalenders: 12 Mini-Monate; Tage mit
 * Veranstaltungen werden nur durch Punkte (eintägig) bzw. durchgehende
 * Linien (mehrtägig) markiert. Ein Klick auf einen Tag öffnet die Woche.
 *
 * @var list<array{first: \DateTimeImmutable, weeks: \DateTimeImmutable[][]}> $months
 * @var array<string, list<array{lane:int, color:string, kind:string, title:string}>> $marks
 * @var int $eventCount
 */

use App\Infrastructure\Http\Controllers\CalendarController;

use function App\Infrastructure\Http\e;

$maxLanes = 3;
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
?>
<?php include __DIR__ . '/_nav.php'; ?>
<style>
    .yr-grid { display:grid; grid-template-columns:repeat(1, minmax(0, 1fr)); gap:1.25rem; }
    @media (min-width: 480px) { .yr-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 900px) { .yr-grid { grid-template-columns:repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 1280px) { .yr-grid { grid-template-columns:repeat(4, minmax(0, 1fr)); } }
    .yr-month h2 { font-size:1rem; font-weight:600; color:#ef4444; margin-bottom:.375rem; }
    .yr-month h2 a:hover { text-decoration:underline; }
    .yr-days { display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); row-gap:.125rem; }
    .yr-wd { text-align:center; font-size:.625rem; font-weight:600; color:rgb(148 163 184); padding-bottom:.125rem; }
    .yr-day { display:flex; flex-direction:column; align-items:stretch; min-height:2.1rem; border-radius:.25rem; }
    .yr-day:hover { background:rgb(241 245 249); }
    .dark .yr-day:hover { background:rgb(30 41 59); }
    .yr-num { text-align:center; font-size:.75rem; line-height:1.25rem; color:rgb(30 41 59); }
    .dark .yr-num { color:rgb(226 232 240); }
    .yr-day.is-weekend .yr-num { color:rgb(100 116 139); }
    .dark .yr-day.is-weekend .yr-num { color:rgb(148 163 184); }
    .yr-day.is-out { visibility:hidden; }
    .yr-day.is-today .yr-num span { display:inline-flex; width:1.25rem; height:1.25rem; align-items:center; justify-content:center; border-radius:9999px; background:#ef4444; color:#fff; font-weight:600; }
    .yr-lanes { display:flex; flex-direction:column; gap:2px; padding-bottom:2px; }
    .yr-lane { position:relative; height:3px; }
    .yr-mark { position:absolute; top:0; height:3px; background:var(--c); }
    .yr-mark.dot { left:50%; width:5px; height:5px; top:-1px; margin-left:-2.5px; border-radius:9999px; }
    /* Linien laufen über die Zellgrenze hinaus, damit sie über die Woche zusammenhängend wirken. */
    .yr-mark.mid { left:-1px; right:-1px; }
    .yr-mark.start { left:30%; right:-1px; border-radius:9999px 0 0 9999px; }
    .yr-mark.end { left:-1px; right:30%; border-radius:0 9999px 9999px 0; }
    .yr-more { text-align:center; font-size:.5625rem; line-height:.5rem; color:rgb(148 163 184); }
</style>

<?php if ($eventCount === 0): ?>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">In diesem Jahr sind keine Veranstaltungen eingetragen.</p>
<?php endif; ?>

<div class="yr-grid">
    <?php foreach ($months as $month): ?>
        <?php $first = $month['first']; ?>
        <div class="yr-month rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
            <h2><a href="/kalender?ansicht=monat&amp;datum=<?= e($first->format('Y-m-d')) ?>"><?= e(CalendarController::monthName($first)) ?></a></h2>
            <div class="yr-days">
                <?php foreach (['M', 'D', 'M', 'D', 'F', 'S', 'S'] as $wd): ?>
                    <div class="yr-wd"><?= e($wd) ?></div>
                <?php endforeach; ?>
                <?php foreach ($month['weeks'] as $week): ?>
                    <?php foreach ($week as $i => $day): ?>
                        <?php
                        $key = $day->format('Y-m-d');
                        $out = $day->format('m') !== $first->format('m');
                        $dayMarks = $out ? [] : ($marks[$key] ?? []);
                        $byLane = [];
                        $hidden = 0;
                        foreach ($dayMarks as $mark) {
                            if ($mark['lane'] < $maxLanes) {
                                $byLane[$mark['lane']] = $mark;
                            } else {
                                $hidden++;
                            }
                        }
                        $lanesUsed = $byLane === [] ? 0 : max(array_keys($byLane)) + 1;
                        $titles = implode("\n", array_unique(array_column($dayMarks, 'title')));
                        $classes = ($i >= 5 ? ' is-weekend' : '') . ($out ? ' is-out' : '') . ($key === $today ? ' is-today' : '');
                        ?>
                        <a class="yr-day<?= $classes ?>" href="/kalender?ansicht=woche&amp;datum=<?= e($key) ?>"
                           title="<?= e($day->format('d.m.Y') . ($titles !== '' ? "\n" . $titles : '')) ?>"<?= $out ? ' tabindex="-1" aria-hidden="true"' : '' ?>>
                            <div class="yr-num"><span><?= (int) $day->format('j') ?></span></div>
                            <?php if ($lanesUsed > 0): ?>
                                <div class="yr-lanes">
                                    <?php for ($lane = 0; $lane < $lanesUsed; $lane++): ?>
                                        <div class="yr-lane">
                                            <?php if (isset($byLane[$lane])): ?>
                                                <?php
                                                $m = $byLane[$lane];
                                                // Linien an Wochengrenzen abrunden (Mo-Anfang/So-Ende).
                                                $kind = $m['kind'];
                                                $style = '--c: ' . $m['color'] . ';';
                                                if ($kind === 'mid' && $i === 0) { $style .= ' left:0; border-top-left-radius:9999px; border-bottom-left-radius:9999px;'; }
                                                if ($kind === 'mid' && $i === 6) { $style .= ' right:0; border-top-right-radius:9999px; border-bottom-right-radius:9999px;'; }
                                                if ($kind === 'start' && $i === 6) { $style .= ' right:0;'; }
                                                if ($kind === 'end' && $i === 0) { $style .= ' left:0;'; }
                                                ?>
                                                <div class="yr-mark <?= e($kind) ?>" style="<?= e($style) ?>"></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                    <?php if ($hidden > 0): ?><div class="yr-more">+<?= $hidden ?></div><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<p class="mt-4 text-xs text-slate-400 dark:text-slate-500">Punkte = eintägige, Linien = mehrtägige Veranstaltungen. Klick auf einen Tag öffnet die Wochenansicht.</p>
