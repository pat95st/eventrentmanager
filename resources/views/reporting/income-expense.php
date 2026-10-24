<?php
/**
 * @var \App\Application\Reporting\Query\IncomeExpenseOverview $overview
 * @var int $currentYear
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$monthNames = [];
for ($m = 1; $m <= 12; $m++) {
    $monthNames[$m] = t('articles.show.month.' . $m);
}

$maxValue = $overview->maxMonthValue();

function moneyFmt(float $value): string
{
    return number_format($value, 2, ',', '.');
}
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('reporting.income_expense.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('reporting.income_expense.subtitle')) ?></p>
    </div>

    <form method="get" action="/berichte/einnahmen-ausgaben" class="flex items-end gap-2">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('reporting.income_expense.year_label')) ?></label>
            <select name="jahr" onchange="this.form.submit()" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <?php for ($y = $currentYear + 1; $y >= $currentYear - 6; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $overview->year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <noscript><button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700"><?= e(t('reporting.income_expense.apply')) ?></button></noscript>
    </form>
</div>

<!-- Jahressummen -->
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('reporting.income_expense.total_income')) ?></p>
        <p class="mt-1 text-2xl font-semibold text-emerald-600 dark:text-emerald-400"><?= moneyFmt($overview->totalIncome()) ?> €</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('reporting.income_expense.total_expense')) ?></p>
        <p class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400"><?= moneyFmt($overview->totalExpense()) ?> €</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('reporting.income_expense.total_saldo')) ?></p>
        <p class="mt-1 text-2xl font-semibold <?= $overview->totalSaldo() >= 0 ? 'text-slate-800 dark:text-slate-100' : 'text-red-600 dark:text-red-400' ?>"><?= moneyFmt($overview->totalSaldo()) ?> €</p>
    </div>
</div>

<!-- Monatsübersicht mit einfacher Balkendarstellung (kein Chart-Framework, reines CSS) -->
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('reporting.income_expense.by_month')) ?></h2>

    <div class="mb-4 flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span><?= e(t('reporting.income_expense.income')) ?></span>
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-red-400"></span><?= e(t('reporting.income_expense.expense')) ?></span>
    </div>

    <div class="space-y-3">
        <?php foreach ($overview->months as $month): ?>
            <div class="flex items-center gap-3">
                <span class="w-20 shrink-0 text-xs text-slate-500 dark:text-slate-400"><?= e($monthNames[$month->month]) ?></span>
                <div class="flex-1 space-y-1">
                    <div class="flex h-3 w-full overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
                        <div class="h-full bg-emerald-500" style="width: <?= min(100, round($month->income / $maxValue * 100, 1)) ?>%"></div>
                    </div>
                    <div class="flex h-3 w-full overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
                        <div class="h-full bg-red-400" style="width: <?= min(100, round($month->expense / $maxValue * 100, 1)) ?>%"></div>
                    </div>
                </div>
                <span class="w-44 shrink-0 text-right text-xs tabular-nums text-slate-600 dark:text-slate-400">
                    <?= moneyFmt($month->income) ?> € / <?= moneyFmt($month->expense) ?> €
                </span>
                <span class="w-28 shrink-0 text-right text-xs font-medium tabular-nums <?= $month->saldo() >= 0 ? 'text-slate-700 dark:text-slate-300' : 'text-red-600 dark:text-red-400' ?>">
                    <?= moneyFmt($month->saldo()) ?> €
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
                <th class="px-4 py-2 text-left"><?= e(t('reporting.income_expense.table.month')) ?></th>
                <th class="px-4 py-2 text-right"><?= e(t('reporting.income_expense.income')) ?></th>
                <th class="px-4 py-2 text-right"><?= e(t('reporting.income_expense.expense')) ?></th>
                <th class="px-4 py-2 text-right"><?= e(t('reporting.income_expense.table.saldo')) ?></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($overview->months as $month): ?>
                <tr>
                    <td class="px-4 py-2 text-slate-700 dark:text-slate-300"><?= e($monthNames[$month->month]) ?></td>
                    <td class="px-4 py-2 text-right text-emerald-700 dark:text-emerald-400"><?= moneyFmt($month->income) ?> €</td>
                    <td class="px-4 py-2 text-right text-red-700 dark:text-red-400"><?= moneyFmt($month->expense) ?> €</td>
                    <td class="px-4 py-2 text-right font-medium <?= $month->saldo() >= 0 ? 'text-slate-800 dark:text-slate-100' : 'text-red-700 dark:text-red-400' ?>"><?= moneyFmt($month->saldo()) ?> €</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-800 dark:bg-slate-800/50">
            <tr>
                <td class="px-4 py-2 text-slate-800 dark:text-slate-100"><?= e(t('reporting.income_expense.table.year_total')) ?></td>
                <td class="px-4 py-2 text-right text-emerald-700 dark:text-emerald-400"><?= moneyFmt($overview->totalIncome()) ?> €</td>
                <td class="px-4 py-2 text-right text-red-700 dark:text-red-400"><?= moneyFmt($overview->totalExpense()) ?> €</td>
                <td class="px-4 py-2 text-right <?= $overview->totalSaldo() >= 0 ? 'text-slate-800 dark:text-slate-100' : 'text-red-700 dark:text-red-400' ?>"><?= moneyFmt($overview->totalSaldo()) ?> €</td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Ausgaben nach Kategorie -->
<div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('reporting.income_expense.by_category')) ?></h2>

    <?php if ($overview->expenseByCategory === []): ?>
        <p class="text-sm text-slate-400 dark:text-slate-500"><?= e(t('reporting.income_expense.no_expenses')) ?></p>
    <?php else: ?>
        <?php
        $categoryLabels = [];
        foreach (\App\Domain\Receipt\ReceiptCategory::cases() as $case) {
            $categoryLabels[$case->value] = $case->label();
        }
        $expenseByCategorySorted = $overview->expenseByCategory;
        arsort($expenseByCategorySorted);
        $totalExpense = $overview->totalExpense();
        ?>
        <ul class="space-y-2">
            <?php foreach ($expenseByCategorySorted as $categoryKey => $sum): ?>
                <li class="flex items-center gap-3 text-sm">
                    <span class="w-32 shrink-0 text-slate-600 dark:text-slate-400"><?= e($categoryLabels[$categoryKey] ?? $categoryKey) ?></span>
                    <div class="h-2.5 flex-1 overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
                        <div class="h-full bg-red-400" style="width: <?= $totalExpense > 0 ? min(100, round($sum / $totalExpense * 100, 1)) : 0 ?>%"></div>
                    </div>
                    <span class="w-28 shrink-0 text-right tabular-nums text-slate-700 dark:text-slate-300"><?= moneyFmt($sum) ?> €</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<p class="mt-6 text-xs text-slate-400 dark:text-slate-500"><?= e(t('reporting.income_expense.basis_hint')) ?></p>
