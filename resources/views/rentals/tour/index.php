<?php
/**
 * Menu "Touren": all events planned as tours (migration 177).
 *
 * @var list<array{rental: \App\Domain\Rental\Rental, customer: string, stops: \App\Domain\Tour\TourStop[]}> $upcoming
 * @var list<array{rental: \App\Domain\Rental\Rental, customer: string, stops: \App\Domain\Tour\TourStop[]}> $past
 */

use function App\Infrastructure\Http\e;

require __DIR__ . '/../series/_styles.php';
$table = static function (array $rows, string $empty): void {
    if ($rows === []) {
        echo '<p class="px-4 py-6 text-sm text-slate-400">' . e($empty) . '</p>';

        return;
    }
    ?>
    <table class="w-full min-w-[640px] text-sm" data-responsive>
        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
            <tr><th class="px-4 py-2.5">Tour</th><th class="px-4 py-2.5" data-priority="2">Kunde</th><th class="px-4 py-2.5" data-priority="1">Zeitraum</th><th class="px-4 py-2.5" data-priority="3">Tourstops</th><th class="px-4 py-2.5"></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($rows as $row): ?>
                <?php $rental = $row['rental']; ?>
                <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800" data-row-link="/veranstaltungen/<?= e($rental->id) ?>/tour">
                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">🚚 <?= $rental->seriesId !== null ? '🔁 ' : '' ?><?= e($rental->eventName) ?></td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($row['customer']) ?></td>
                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= e($rental->startDate->format('d.m.Y')) ?> – <?= e($rental->endDate->format('d.m.Y')) ?></td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                        <?= e(implode(' → ', array_map(static fn ($s) => $s->title(), $row['stops']))) ?>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                        <a href="/veranstaltungen/<?= e($rental->id) ?>/tour/route" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Route</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
};
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Touren</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Veranstaltungen mit mehreren Tourstops. Eine neue Tour entsteht aus einer Veranstaltung über „…“ › „Als Tour planen“.</p>
</div>

<h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Aktuelle und kommende Touren</h2>
<div class="mb-8 overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php $table($upcoming, 'Keine aktuellen oder kommenden Touren.'); ?>
</div>

<?php if ($past !== []): ?>
    <details>
        <summary class="mb-2 cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-300">Vergangene Touren (<?= count($past) ?>)</summary>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <?php $table($past, ''); ?>
        </div>
    </details>
<?php endif; ?>
