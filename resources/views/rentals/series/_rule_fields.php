<?php
/**
 * Rhythm fields (RecurrenceRule::fromInput()).
 *
 * @var array<string, mixed> $rule
 * @var bool $showEnd
 */

use App\Domain\RentalSeries\RecurrenceRule;
use App\Domain\RentalSeries\RecurrenceType;
use function App\Infrastructure\Http\e;

require __DIR__ . '/_styles.php';
$value = static fn (string $key): string => is_scalar($rule[$key] ?? null) ? (string) $rule[$key] : '';
$type = $value('type') !== '' ? $value('type') : 'weekly';
$weekdays = array_map('strval', is_array($rule['weekdays'] ?? null) ? $rule['weekdays'] : []);
$dayNames = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
$longDayNames = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
$endMode = $value('end_mode') === 'date' ? 'date' : 'count';
$showEnd ??= true;
?>
<div class="space-y-4" data-series-rule>
    <div>
        <label class="<?= $label ?>" for="series-type">Rhythmus</label>
        <select id="series-type" name="type" class="<?= $input ?>" data-series-type>
            <?php foreach (RecurrenceType::cases() as $case): ?>
                <option value="<?= e($case->value) ?>"<?= $case->value === $type ? ' selected' : '' ?>><?= e($case->label()) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div data-series-show="weekly monthly_day monthly_weekday yearly">
        <label class="<?= $label ?>" for="series-interval">Alle … <span data-series-unit>Wochen/Monate/Jahre</span></label>
        <input id="series-interval" type="number" min="1" max="52" name="interval" value="<?= e($value('interval') !== '' ? $value('interval') : '1') ?>" class="<?= $input ?> max-w-[8rem]">
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">1 = jede Woche bzw. jeden Monat/jedes Jahr, 2 = jede zweite usw.</p>
    </div>

    <fieldset data-series-show="weekly">
        <legend class="<?= $label ?>">An diesen Wochentagen</legend>
        <div class="mt-1 flex flex-wrap gap-2">
            <?php foreach ($dayNames as $number => $name): ?>
                <label class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700">
                    <input type="checkbox" name="weekdays[]" value="<?= $number ?>"<?= in_array((string) $number, $weekdays, true) ? ' checked' : '' ?>> <?= e($name) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div data-series-show="monthly_day">
        <label class="<?= $label ?>" for="series-month-day">Am Tag des Monats</label>
        <input id="series-month-day" type="number" min="1" max="31" name="month_day" value="<?= e($value('month_day')) ?>" placeholder="wie erster Termin" class="<?= $input ?> max-w-[8rem]">
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Gibt es den Tag in einem Monat nicht (z. B. 31.), wird der letzte Tag des Monats genommen.</p>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:max-w-md" data-series-show="monthly_weekday">
        <div>
            <label class="<?= $label ?>" for="series-week">Am</label>
            <select id="series-week" name="week_of_month" class="<?= $input ?>">
                <option value="">wie erster Termin</option>
                <?php foreach ([1 => '1.', 2 => '2.', 3 => '3.', 4 => '4.', -1 => 'letzten'] as $week => $weekLabel): ?>
                    <option value="<?= $week ?>"<?= $value('week_of_month') === (string) $week ? ' selected' : '' ?>><?= e($weekLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="<?= $label ?>" for="series-weekday">Wochentag</label>
            <select id="series-weekday" name="weekday" class="<?= $input ?>">
                <option value="">wie erster Termin</option>
                <?php foreach ($longDayNames as $number => $name): ?>
                    <option value="<?= $number ?>"<?= $value('weekday') === (string) $number ? ' selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <p class="text-xs text-slate-500 dark:text-slate-400" data-series-show="yearly">Jedes Jahr am selben Tag wie der erste Termin (29.02. wird in anderen Jahren zum 28.02.).</p>

    <div data-series-show="manual">
        <label class="<?= $label ?>" for="series-manual">Weitere Termine (Startdatum, eines pro Zeile)</label>
        <textarea id="series-manual" name="manual_dates" rows="6" placeholder="14.03.2027&#10;02.05.2027" class="<?= $input ?> font-mono"><?= e($value('manual_dates')) ?></textarea>
    </div>

    <?php if ($showEnd): ?>
        <fieldset data-series-show="weekly monthly_day monthly_weekday yearly">
            <legend class="<?= $label ?>">Ende der Serie</legend>
            <div class="mt-1 space-y-2 text-sm">
                <label class="flex flex-wrap items-center gap-2">
                    <input type="radio" name="end_mode" value="count"<?= $endMode === 'count' ? ' checked' : '' ?>>
                    nach
                    <input type="number" min="2" max="<?= RecurrenceRule::MAX_OCCURRENCES ?>" name="occurrence_count" value="<?= e($value('occurrence_count')) ?>" class="w-20 rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900">
                    Terminen (inkl. dem ersten)
                </label>
                <label class="flex flex-wrap items-center gap-2">
                    <input type="radio" name="end_mode" value="date"<?= $endMode === 'date' ? ' checked' : '' ?>>
                    bis einschließlich
                    <input type="date" name="ends_on" value="<?= e($value('ends_on')) ?>" class="rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900">
                </label>
                <p class="text-xs text-slate-500 dark:text-slate-400">Höchstens <?= RecurrenceRule::MAX_OCCURRENCES ?> Termine bzw. <?= RecurrenceRule::MAX_YEARS ?> Jahre auf einmal – verlängern lässt sich die Serie später jederzeit.</p>
            </div>
        </fieldset>
    <?php endif; ?>
</div>
<script>
(() => {
    const root = document.currentScript.previousElementSibling;
    const select = root.querySelector('[data-series-type]');
    const units = { weekly: 'Wochen', monthly_day: 'Monate', monthly_weekday: 'Monate', yearly: 'Jahre' };
    const update = () => {
        root.querySelectorAll('[data-series-show]').forEach((el) => {
            el.hidden = !el.dataset.seriesShow.split(' ').includes(select.value);
        });
        const unit = root.querySelector('[data-series-unit]');
        if (unit) unit.textContent = units[select.value] || '';
    };
    select.addEventListener('change', update);
    update();
})();
</script>
