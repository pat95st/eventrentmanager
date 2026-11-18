<?php
/**
 * Menu "Wiederkehrende Veranstaltungen": all series (migration 176).
 *
 * @var list<array{series: \App\Domain\RentalSeries\RentalSeries, occurrences: \App\Domain\Rental\Rental[], next: ?\App\Domain\Rental\Rental, first: ?\App\Domain\Rental\Rental, last: ?\App\Domain\Rental\Rental}> $active
 * @var list<array{series: \App\Domain\RentalSeries\RentalSeries, occurrences: \App\Domain\Rental\Rental[], next: ?\App\Domain\Rental\Rental, first: ?\App\Domain\Rental\Rental, last: ?\App\Domain\Rental\Rental}> $ended
 */

use function App\Infrastructure\Http\e;

$table = static function (array $rows, string $empty): void {
    if ($rows === []) {
        echo '<p class="px-4 py-6 text-sm text-slate-400">' . e($empty) . '</p>';

        return;
    }
    ?>
    <table class="w-full min-w-[640px] text-sm">
        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
            <tr><th class="px-4 py-2.5">Serie</th><th class="px-4 py-2.5">Rhythmus</th><th class="px-4 py-2.5">Termine</th><th class="px-4 py-2.5">Nächster Termin</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($rows as $row): ?>
                <?php $series = $row['series']; ?>
                <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800" data-row-link="/serien/<?= e($series->id) ?>">
                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">🔁 <?= e($series->nameTemplate ?? ($row['first']?->eventName ?? '–')) ?><?= $series->isEnded() ? ' <span class="text-xs font-normal text-slate-500">(beendet)</span>' : '' ?></td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= e(ucfirst($series->rule->describe())) ?></td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-400">
                        <?= count($row['occurrences']) ?>
                        <?php if ($row['first'] !== null && $row['last'] !== null): ?>
                            <span class="block font-mono text-xs"><?= e($row['first']->startDate->format('d.m.Y')) ?> – <?= e($row['last']->startDate->format('d.m.Y')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                        <?php if ($row['next'] !== null): ?>
                            <a href="/veranstaltungen/<?= e($row['next']->id) ?>" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($row['next']->startDate->format('d.m.Y')) ?></a>
                            <?= $row['next']->eventLocation !== null ? '<span class="block text-xs">' . e($row['next']->eventLocation) . '</span>' : '' ?>
                        <?php else: ?>–<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
};
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Wiederkehrende Veranstaltungen</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Serien von Veranstaltungen. Eine neue Serie entsteht aus einer Veranstaltung über „…“ › „Wiederkehrend machen“.</p>
</div>

<h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Laufende Serien</h2>
<div class="mb-8 overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php $table($active, 'Keine laufenden Serien.'); ?>
</div>

<?php if ($ended !== []): ?>
    <details>
        <summary class="mb-2 cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-300">Beendete Serien (<?= count($ended) ?>)</summary>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <?php $table($ended, ''); ?>
        </div>
    </details>
<?php endif; ?>
