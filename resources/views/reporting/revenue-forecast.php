<?php
/**
 * @var \App\Application\Reporting\Query\RevenueForecast $forecast
 * @var int $currentYear
 * @var int $fromYear
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$maxValue = $forecast->maxYearValue();

function moneyFmt(float $value): string
{
    return number_format($value, 2, ',', '.');
}
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('reporting.revenue_forecast.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('reporting.revenue_forecast.subtitle')) ?></p>
    </div>

    <form method="get" action="/berichte/umsatzprognose" class="flex items-end gap-2">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('reporting.revenue_forecast.from_year_label')) ?></label>
            <select name="ab" onchange="this.form.submit()" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <?php for ($y = $currentYear + 5; $y >= $currentYear - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $fromYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <noscript><button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700"><?= e(t('reporting.revenue_forecast.apply')) ?></button></noscript>
    </form>
</div>

<!-- Gesamtsumme -->
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-1">
    <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('reporting.revenue_forecast.grand_total')) ?></p>
        <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100"><?= moneyFmt($forecast->grandTotal()) ?> €</p>
    </div>
</div>

<!-- Jahresübersicht mit einfacher Balkendarstellung (kein Chart-Framework, reines CSS) -->
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('reporting.revenue_forecast.by_year')) ?></h2>

    <div class="mb-4 flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-400"></span><?= e(t('reporting.revenue_forecast.offers')) ?></span>
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-blue-400"></span><?= e(t('reporting.revenue_forecast.order_confirmations')) ?></span>
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span><?= e(t('reporting.revenue_forecast.invoices')) ?></span>
    </div>

    <div class="space-y-3">
        <?php foreach ($forecast->years as $forecastYear): ?>
            <div class="flex items-center gap-3">
                <span class="w-16 shrink-0 text-xs text-slate-500 dark:text-slate-400"><?= $forecastYear->year ?></span>
                <div class="flex-1">
                    <div class="flex h-3 w-full overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
                        <div class="h-full bg-amber-400" style="width: <?= min(100, round($forecastYear->offerAmount / $maxValue * 100, 1)) ?>%"></div>
                        <div class="h-full bg-blue-400" style="width: <?= min(100, round($forecastYear->orderConfirmationAmount / $maxValue * 100, 1)) ?>%"></div>
                        <div class="h-full bg-emerald-500" style="width: <?= min(100, round($forecastYear->invoiceAmount / $maxValue * 100, 1)) ?>%"></div>
                    </div>
                </div>
                <span class="w-32 shrink-0 text-right text-xs font-medium tabular-nums text-slate-700 dark:text-slate-300">
                    <?= moneyFmt($forecastYear->total()) ?> €
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Detailtabelle -->
<div class="mb-6 overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
                <th class="px-4 py-2 text-left"><?= e(t('reporting.revenue_forecast.table.year')) ?></th>
                <th class="px-4 py-2 text-right"><?= e(t('reporting.revenue_forecast.offers')) ?></th>
                <th class="px-4 py-2 text-right"><?= e(t('reporting.revenue_forecast.order_confirmations')) ?></th>
                <th class="px-4 py-2 text-right"><?= e(t('reporting.revenue_forecast.invoices')) ?></th>
                <th class="px-4 py-2 text-right"><?= e(t('reporting.revenue_forecast.table.total')) ?></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($forecast->years as $forecastYear): ?>
                <tr>
                    <td class="px-4 py-2 text-slate-700 dark:text-slate-300"><?= $forecastYear->year ?></td>
                    <td class="px-4 py-2 text-right text-amber-700 dark:text-amber-400"><?= moneyFmt($forecastYear->offerAmount) ?> €</td>
                    <td class="px-4 py-2 text-right text-blue-700 dark:text-blue-400"><?= moneyFmt($forecastYear->orderConfirmationAmount) ?> €</td>
                    <td class="px-4 py-2 text-right text-emerald-700 dark:text-emerald-400"><?= moneyFmt($forecastYear->invoiceAmount) ?> €</td>
                    <td class="px-4 py-2 text-right font-medium text-slate-800 dark:text-slate-100"><?= moneyFmt($forecastYear->total()) ?> €</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-800 dark:bg-slate-800/50">
            <tr>
                <td class="px-4 py-2 text-slate-800 dark:text-slate-100"><?= e(t('reporting.revenue_forecast.table.grand_total')) ?></td>
                <td class="px-4 py-2 text-right text-amber-700 dark:text-amber-400"><?= moneyFmt(array_sum(array_map(fn ($y) => $y->offerAmount, $forecast->years))) ?> €</td>
                <td class="px-4 py-2 text-right text-blue-700 dark:text-blue-400"><?= moneyFmt(array_sum(array_map(fn ($y) => $y->orderConfirmationAmount, $forecast->years))) ?> €</td>
                <td class="px-4 py-2 text-right text-emerald-700 dark:text-emerald-400"><?= moneyFmt(array_sum(array_map(fn ($y) => $y->invoiceAmount, $forecast->years))) ?> €</td>
                <td class="px-4 py-2 text-right text-slate-800 dark:text-slate-100"><?= moneyFmt($forecast->grandTotal()) ?> €</td>
            </tr>
        </tfoot>
    </table>
</div>

<p class="mt-6 text-xs text-slate-400 dark:text-slate-500"><?= e(t('reporting.revenue_forecast.basis_hint')) ?></p>
