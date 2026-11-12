<?php
/**
 * @var \App\Application\Depreciation\DepreciationReport $report
 * @var int $currentYear
 * @var bool $hideWrittenOff
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$year = $report->year;
$money = static fn (float $value): string => number_format($value, 2, ',', '.');

// "Abgeschriebene ausblenden": Exemplare, die im Jahr weder Abschreibung noch Abgang noch Restbuchwert haben.
$rows = $report->rows;
$hiddenCount = 0;
if ($hideWrittenOff) {
    $rows = array_values(array_filter($rows, static fn ($row) => $row->schedule->amountIn($year) > 0 || $row->schedule->disposalIn($year) > 0 || $row->schedule->bookValueAtEndOf($year) > 0));
    $hiddenCount = count($report->rows) - count($rows);
}
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('reporting.depreciation.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('reporting.depreciation.subtitle')) ?></p>
    </div>

    <div class="flex flex-wrap items-end gap-2">
        <form method="get" action="/berichte/abschreibungen" class="flex items-end gap-3">
            <div>
                <label for="jahr" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('reporting.income_expense.year_label')) ?></label>
                <select id="jahr" name="jahr" onchange="this.form.submit()" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <?php for ($y = max($currentYear + 5, $year); $y >= min($currentYear - 10, $year); $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="abgeschrieben" value="0" <?= $hideWrittenOff ? 'checked' : '' ?> onchange="this.form.submit()"
                       class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('reporting.depreciation.hide_written_off')) ?>
            </label>
            <noscript><button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700"><?= e(t('reporting.income_expense.apply')) ?></button></noscript>
        </form>
        <a href="/berichte/abschreibungen.csv?jahr=<?= $year ?>" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('reporting.depreciation.csv')) ?></a>
        <a href="/einstellungen/abschreibungen" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('reporting.depreciation.settings')) ?></a>
    </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('reporting.depreciation.total_depreciation', ['year' => (string) $year])) ?></p>
        <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100"><?= $money($report->totalDepreciation()) ?> €</p>
        <?php if ($report->totalDisposal() > 0): ?>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?= e(t('reporting.depreciation.plus_disposal', ['amount' => $money($report->totalDisposal())])) ?></p>
        <?php endif; ?>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('reporting.depreciation.total_book_value', ['year' => (string) $year])) ?></p>
        <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100"><?= $money($report->totalBookValueEnd()) ?> €</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?= e(t('reporting.depreciation.book_value_start', ['year' => (string) $year, 'amount' => $money($report->totalBookValueStart())])) ?></p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('reporting.depreciation.total_cost')) ?></p>
        <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100"><?= $money($report->totalCost()) ?> €</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?= e(t('reporting.depreciation.item_count', ['count' => (string) count($report->rows)])) ?></p>
    </div>
</div>

<?php if ($report->incomplete !== []): ?>
    <details class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
        <summary class="cursor-pointer font-medium"><?= e(t('reporting.depreciation.incomplete', ['count' => (string) count($report->incomplete)])) ?></summary>
        <ul class="mt-3 space-y-1">
            <?php foreach ($report->incomplete as $asset): ?>
                <li>
                    <a href="/inventurnummern/<?= e($asset->inventoryNumberId) ?>" class="font-mono underline"><?= e($asset->number) ?></a>
                    <?= e($asset->label()) ?> —
                    <?= e(implode(', ', array_filter([
                        $asset->acquisitionDate === null ? t('reporting.depreciation.missing_date') : null,
                        $asset->purchasePrice === null || (float) $asset->purchasePrice <= 0 ? t('reporting.depreciation.missing_price') : null,
                    ]))) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </details>
<?php endif; ?>

<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
                <th class="px-4 py-2 text-left"><?= e(t('reporting.depreciation.table.inventory_number')) ?></th>
                <th class="px-4 py-2 text-left"><?= e(t('reporting.depreciation.table.article')) ?></th>
                <th class="whitespace-nowrap px-4 py-2 text-left"><?= e(t('reporting.depreciation.table.acquired')) ?></th>
                <th class="whitespace-nowrap px-4 py-2 text-right"><?= e(t('reporting.depreciation.table.cost')) ?></th>
                <th class="whitespace-nowrap px-4 py-2 text-right"><?= e(t('reporting.depreciation.table.depreciation', ['year' => (string) $year])) ?></th>
                <th class="whitespace-nowrap px-4 py-2 text-right"><?= e(t('reporting.depreciation.table.book_value', ['year' => (string) $year])) ?></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php if ($rows === []): ?>
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400 dark:text-slate-500"><?= e(t('reporting.depreciation.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $schedule = $row->schedule; $disposal = $schedule->disposalIn($year); ?>
                <tr>
                    <td class="whitespace-nowrap px-4 py-2">
                        <a href="/inventurnummern/<?= e($row->asset->inventoryNumberId) ?>" class="font-mono text-brand-700 hover:underline dark:text-brand-400"><?= e($row->asset->number) ?></a>
                    </td>
                    <td class="px-4 py-2 text-slate-700 dark:text-slate-300" style="min-width: 12rem">
                        <?= e($row->asset->label()) ?>
                        <?php if ($row->asset->categoryName !== null): ?>
                            <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e($row->asset->categoryName) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 text-slate-600 dark:text-slate-400">
                        <?= e($schedule->acquisitionDate->format('d.m.Y')) ?>
                        <span class="block text-xs text-slate-400 dark:text-slate-500"><?= $schedule->lowValueAsset ? e(t('reporting.depreciation.low_value')) : e(t('reporting.depreciation.years', ['count' => (string) $schedule->usefulLifeYears])) ?></span>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-slate-700 dark:text-slate-300"><?= $money($schedule->cost) ?> €</td>
                    <td class="px-4 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100">
                        <span class="whitespace-nowrap"><?= $money($schedule->amountIn($year)) ?> €</span>
                        <?php if ($schedule->disposalDate !== null && (int) $schedule->disposalDate->format('Y') === $year): ?>
                            <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e(t('reporting.depreciation.sold', ['date' => $schedule->disposalDate->format('d.m.Y'), 'amount' => $money($disposal)])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 text-right font-medium tabular-nums text-slate-800 dark:text-slate-100"><?= $money($schedule->bookValueAtEndOf($year)) ?> €</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <?php if ($rows !== []): ?>
            <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-800 dark:bg-slate-800/50">
                <tr>
                    <td colspan="3" class="px-4 py-2 text-slate-800 dark:text-slate-100">
                        <?= e(t('reporting.depreciation.table.total')) ?>
                        <?php if ($hiddenCount > 0): ?>
                            <span class="font-normal text-slate-400 dark:text-slate-500"><?= e(t('reporting.depreciation.hidden_count', ['count' => (string) $hiddenCount])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100"><?= $money($report->totalCost()) ?> €</td>
                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100"><?= $money($report->totalDepreciation()) ?> €</td>
                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100"><?= $money($report->totalBookValueEnd()) ?> €</td>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>

<p class="mt-6 text-xs text-slate-400 dark:text-slate-500"><?= e(t('reporting.depreciation.basis_hint')) ?></p>
