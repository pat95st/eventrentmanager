<?php
/**
 * Gemeinsame Kopfzeile aller Kalenderansichten: Ansichtsumschalter,
 * vor/zurück, Heute. Erwartet $view, $label, $prevDate, $nextDate.
 *
 * @var string $view
 * @var string $label
 * @var string $prevDate
 * @var string $nextDate
 */

use App\Infrastructure\Http\Controllers\CalendarController;

use function App\Infrastructure\Http\e;

$calUrl = static fn (string $v, ?string $date = null): string => '/kalender?ansicht=' . rawurlencode($v) . ($date !== null ? '&datum=' . rawurlencode($date) : '');
$anchorDate = isset($anchor) && $anchor instanceof DateTimeImmutable ? $anchor->format('Y-m-d') : null;
?>
<style>
    .cal-seg { display:inline-flex; border:1px solid rgb(203 213 225); border-radius:.5rem; overflow:hidden; }
    .cal-seg a { padding:.375rem .75rem; font-size:.8125rem; font-weight:500; color:rgb(71 85 105); }
    .cal-seg a + a { border-left:1px solid rgb(203 213 225); }
    .cal-seg a:hover { background:rgb(248 250 252); }
    .cal-seg a[aria-current="page"] { background:rgb(15 23 42); color:#fff; }
    .dark .cal-seg { border-color:rgb(51 65 85); }
    .dark .cal-seg a { color:rgb(203 213 225); }
    .dark .cal-seg a + a { border-color:rgb(51 65 85); }
    .dark .cal-seg a:hover { background:rgb(30 41 59); }
    .dark .cal-seg a[aria-current="page"] { background:rgb(241 245 249); color:rgb(15 23 42); }
    .cal-btn { display:inline-flex; align-items:center; justify-content:center; border:1px solid rgb(203 213 225); border-radius:.375rem; padding:.375rem .5rem; color:rgb(100 116 139); font-size:.8125rem; }
    .cal-btn:hover { background:rgb(248 250 252); }
    .dark .cal-btn { border-color:rgb(51 65 85); color:rgb(148 163 184); }
    .dark .cal-btn:hover { background:rgb(30 41 59); }
</style>
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Kalender</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($label) ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <nav class="cal-seg" aria-label="Kalenderansicht">
            <?php foreach (CalendarController::VIEWS as $key => $title): ?>
                <a href="<?= e($calUrl($key, $key === 'liste' ? null : $anchorDate)) ?>" <?= $key === $view ? 'aria-current="page"' : '' ?> data-cal-view="<?= e($key) ?>"><?= e($title) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="flex items-center gap-1">
            <a class="cal-btn" href="<?= e($calUrl($view, $prevDate)) ?>" aria-label="Zurück">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 010 1.06L8.06 11l4.73 4.71a.75.75 0 11-1.06 1.06l-5.25-5.25a.75.75 0 010-1.06l5.25-5.25a.75.75 0 011.06 0z" clip-rule="evenodd"/></svg>
            </a>
            <a class="cal-btn" href="<?= e($calUrl($view)) ?>">Heute</a>
            <a class="cal-btn" href="<?= e($calUrl($view, $nextDate)) ?>" aria-label="Weiter">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 010-1.06L11.94 9 7.21 4.29a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06 0z" clip-rule="evenodd"/></svg>
            </a>
        </div>
    </div>
</div>
<script>
    // Auf Mobilgeräten ohne ausdrücklich gewählte Ansicht direkt die Liste
    // der nächsten Veranstaltungen zeigen; die Wahl wird gemerkt.
    (function () {
        var params = new URLSearchParams(location.search);
        if (!params.has('ansicht') && !params.has('monat') && window.matchMedia('(max-width: 639px)').matches && <?= json_encode($view) ?> !== 'liste') {
            var stored = null;
            try { stored = localStorage.getItem('calendarView'); } catch (e) {}
            location.replace('/kalender?ansicht=' + encodeURIComponent(stored || 'liste'));
        }
        document.querySelectorAll('[data-cal-view]').forEach(function (a) {
            a.addEventListener('click', function () { try { localStorage.setItem('calendarView', a.dataset.calView); } catch (e) {} });
        });
    })();
</script>
