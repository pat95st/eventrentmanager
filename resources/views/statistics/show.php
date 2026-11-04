<?php
/** @var \App\Application\Statistics\Query\StatisticChart $chart */
/** @var int $months */
/** @var int[] $periods */
/** @var \App\Application\Statistics\StatisticKey[] $others */

use function App\Infrastructure\Http\e;

$key = $chart->key;
// Spalten mit Zahlen/Beträgen rechtsbündig (anhand der ersten Zeile erkannt).
$numeric = [];
foreach ($chart->columns as $i => $_) {
    $sample = $chart->rows[0][$i] ?? '';
    $numeric[$i] = $i > 0 && preg_match('/^[-–]?\d[\d.,]*( (€|%))?$|^–$/u', $sample) === 1;
}
?>
<div class="mb-4">
    <a href="/" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">← Dashboard</a>
</div>

<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($key->label()) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($key->description()) ?></p>
    </div>
    <?php if ($key->usesPeriod()): ?>
        <nav class="flex gap-1 rounded-md border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900" aria-label="Zeitraum">
            <?php foreach ($periods as $period): ?>
                <?php $active = $period === $months; ?>
                <a href="/statistiken/<?= e($key->value) ?>?monate=<?= $period ?>"
                   class="rounded px-3 py-1 text-sm font-medium <?= $active ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' ?>"
                   <?= $active ? 'aria-current="page"' : '' ?>><?= $period ?> Monate</a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
        <?php if ($chart->isEmpty()): ?>
            <div class="flex items-center justify-center text-sm text-slate-400 dark:text-slate-500" style="min-height:320px">Für diesen Zeitraum liegen noch keine Daten vor.</div>
        <?php else: ?>
            <div class="relative" style="height:<?= $chart->type === 'barH' ? max(320, count($chart->labels) * 26) : 360 ?>px">
                <canvas data-chart="<?= e($chart->toChartJson()) ?>" role="img" aria-label="<?= e($key->label()) ?>"></canvas>
            </div>
        <?php endif; ?>
    </div>
    <div class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="font-bold text-slate-900 dark:text-slate-100" style="font-size:1.75rem;line-height:2.25rem;overflow-wrap:anywhere"><?= e($chart->headline) ?></p>
            <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($chart->headlineCaption) ?></p>
        </div>
        <?php if ($others !== []): ?>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Weitere Statistiken</h2>
                <ul class="space-y-1">
                    <?php foreach ($others as $other): ?>
                        <li><a href="/statistiken/<?= e($other->value) ?>" class="text-sm text-brand-600 hover:underline dark:text-brand-400"><?= e($other->label()) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($chart->rows !== []): ?>
    <div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                <tr>
                    <?php foreach ($chart->columns as $i => $column): ?>
                        <th class="whitespace-nowrap px-4 py-2 <?= $numeric[$i] ? 'text-right' : '' ?>"><?= e($column) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($chart->rows as $r => $row): ?>
                    <?php $link = $chart->links[$r] ?? null; $isTotal = $row[0] === 'Summe'; ?>
                    <tr class="<?= $isTotal ? 'font-semibold' : 'hover:bg-slate-50 dark:hover:bg-slate-800' ?>">
                        <?php foreach ($row as $i => $cell): ?>
                            <td class="px-4 py-2 <?= ($numeric[$i] ?? false) ? 'whitespace-nowrap text-right' : '' ?> text-slate-700 dark:text-slate-300" style="font-variant-numeric:tabular-nums">
                                <?php if ($i === 0 && $link !== null): ?>
                                    <a href="<?= e($link) ?>" class="font-medium text-brand-600 hover:underline dark:text-brand-400"><?= e($cell) ?></a>
                                <?php else: ?>
                                    <?= e($cell) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($key === \App\Application\Statistics\StatisticKey::Inspections && !$chart->isEmpty()): ?>
    <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">Alle prüfpflichtigen Exemplare sind gültig geprüft.</p>
<?php endif; ?>

<?php if ($key === \App\Application\Statistics\StatisticKey::Revenue): ?>
    <p class="mt-4 text-sm"><a href="/berichte/einnahmen-ausgaben" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Zur ausführlichen Einnahmen-/Ausgabenübersicht →</a></p>
<?php endif; ?>

<script src="/assets/js/dashboard-charts.js" defer></script>
