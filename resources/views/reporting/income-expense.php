<?php
/**
 * @var \App\Application\Reporting\Query\IncomeExpenseOverview $overview
 * @var int $currentYear
 * @var \App\Domain\RevenueGoal\RevenueGoalProgress[] $goalProgress
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

<!-- Umsatzziele (Einstellungen › Umsatzziele): Stand gemessen an den Einnahmen des Jahres -->
<?php if (($goalProgress ?? []) !== []): ?>
    <?php
    $goalStyles = [
        \App\Domain\RevenueGoal\RevenueGoalProgress::STATUS_OPEN => ['bar' => 'bg-brand-600', 'badge' => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700'],
        \App\Domain\RevenueGoal\RevenueGoalProgress::STATUS_REACHED => ['bar' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700'],
        \App\Domain\RevenueGoal\RevenueGoalProgress::STATUS_OK => ['bar' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700'],
        \App\Domain\RevenueGoal\RevenueGoalProgress::STATUS_WARNING => ['bar' => 'bg-amber-400', 'badge' => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700'],
        \App\Domain\RevenueGoal\RevenueGoalProgress::STATUS_EXCEEDED => ['bar' => 'bg-red-400', 'badge' => 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-400 dark:ring-red-800'],
    ];
    ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('reporting.revenue_goals.title', ['year' => (string) $overview->year])) ?></h2>
            <a href="/einstellungen/umsatzziele?jahr=<?= $overview->year ?>" class="text-xs text-brand-700 hover:underline dark:text-brand-400"><?= e(t('reporting.revenue_goals.edit')) ?></a>
        </div>
        <div class="space-y-4">
            <?php foreach ($goalProgress as $progress): ?>
                <?php
                $status = $progress->status();
                $isLimit = $progress->goal->kind === \App\Domain\RevenueGoal\RevenueGoalKind::Limit;
                $remaining = $progress->remaining();
                if ($remaining >= 0) {
                    $remainingText = t($isLimit ? 'reporting.revenue_goals.remaining_limit' : 'reporting.revenue_goals.remaining_target', ['amount' => moneyFmt($remaining)]);
                } else {
                    $remainingText = t($isLimit ? 'reporting.revenue_goals.over_limit' : 'reporting.revenue_goals.over_target', ['amount' => moneyFmt(-$remaining)]);
                }
                ?>
                <div>
                    <div class="mb-1 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-sm">
                        <span class="font-medium text-slate-800 dark:text-slate-200">
                            <?= e($progress->goal->name) ?>
                            <span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium <?= $goalStyles[$status]['badge'] ?>"><?= e(t('reporting.revenue_goals.status.' . $status)) ?></span>
                        </span>
                        <span class="tabular-nums text-slate-600 dark:text-slate-400">
                            <?= moneyFmt($progress->actual) ?> € / <?= moneyFmt($progress->target()) ?> € (<?= e(number_format($progress->percent(), 1, ',', '.')) ?> %)
                        </span>
                    </div>
                    <div class="flex h-3 w-full overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
                        <div class="h-full <?= $goalStyles[$status]['bar'] ?>" style="width: <?= min(100, max(0, $progress->percent())) ?>%"></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        <?= e($remainingText) ?>
                        <?php if ($progress->goal->hasOverrideFor($overview->year)): ?>
                            · <?= e(t('reporting.revenue_goals.year_override', ['year' => (string) $overview->year, 'amount' => moneyFmt((float) $progress->goal->amount)])) ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="mt-4 text-xs text-slate-400 dark:text-slate-500"><?= e(t('reporting.revenue_goals.basis_hint')) ?></p>
    </div>
<?php endif; ?>

<style>
    /* Mobil: Monat + Balken in einer Zeile, Beträge und Saldo darunter. */
    @media (max-width: 639px) {
        .ie-row { flex-wrap: wrap; row-gap: .25rem; }
        .ie-row > .flex-1 { flex-basis: calc(100% - 5rem - .75rem); }
        .ie-amounts { width: auto; flex: 1 1 auto; text-align: left; padding-left: calc(5rem + .75rem); }
        .ie-saldo { width: auto; }
    }
</style>

<!-- Monatsübersicht mit einfacher Balkendarstellung (kein Chart-Framework, reines CSS) -->
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('reporting.income_expense.by_month')) ?></h2>

    <div class="mb-4 flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span><?= e(t('reporting.income_expense.income')) ?></span>
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-red-400"></span><?= e(t('reporting.income_expense.expense')) ?></span>
    </div>

    <div class="space-y-3">
        <?php foreach ($overview->months as $month): ?>
            <div class="ie-row flex items-center gap-3">
                <span class="ie-month w-20 shrink-0 text-xs text-slate-500 dark:text-slate-400"><?= e($monthNames[$month->month]) ?></span>
                <div class="flex-1 space-y-1">
                    <div class="flex h-3 w-full overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
                        <div class="h-full bg-emerald-500" style="width: <?= min(100, round($month->income / $maxValue * 100, 1)) ?>%"></div>
                    </div>
                    <div class="flex h-3 w-full overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
                        <div class="h-full bg-red-400" style="width: <?= min(100, round($month->expense / $maxValue * 100, 1)) ?>%"></div>
                    </div>
                </div>
                <span class="ie-amounts w-44 shrink-0 text-right text-xs tabular-nums text-slate-600 dark:text-slate-400">
                    <?= moneyFmt($month->income) ?> € / <?= moneyFmt($month->expense) ?> €
                </span>
                <span class="ie-saldo w-28 shrink-0 text-right text-xs font-medium tabular-nums <?= $month->saldo() >= 0 ? 'text-slate-700 dark:text-slate-300' : 'text-red-600 dark:text-red-400' ?>">
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
                <th class="whitespace-nowrap px-4 py-2 text-right"><?= e(t('reporting.income_expense.income')) ?></th>
                <th class="whitespace-nowrap px-4 py-2 text-right"><?= e(t('reporting.income_expense.expense')) ?></th>
                <th class="whitespace-nowrap px-4 py-2 text-right"><?= e(t('reporting.income_expense.table.saldo')) ?></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($overview->months as $month): ?>
                <tr>
                    <td class="px-4 py-2 text-slate-700 dark:text-slate-300"><?= e($monthNames[$month->month]) ?></td>
                    <td class="whitespace-nowrap px-4 py-2 text-right text-emerald-700 dark:text-emerald-400"><?= moneyFmt($month->income) ?> €</td>
                    <td class="whitespace-nowrap px-4 py-2 text-right text-red-700 dark:text-red-400"><?= moneyFmt($month->expense) ?> €</td>
                    <td class="whitespace-nowrap px-4 py-2 text-right font-medium <?= $month->saldo() >= 0 ? 'text-slate-800 dark:text-slate-100' : 'text-red-700 dark:text-red-400' ?>"><?= moneyFmt($month->saldo()) ?> €</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-800 dark:bg-slate-800/50">
            <tr>
                <td class="px-4 py-2 text-slate-800 dark:text-slate-100"><?= e(t('reporting.income_expense.table.year_total')) ?></td>
                <td class="whitespace-nowrap px-4 py-2 text-right text-emerald-700 dark:text-emerald-400"><?= moneyFmt($overview->totalIncome()) ?> €</td>
                <td class="whitespace-nowrap px-4 py-2 text-right text-red-700 dark:text-red-400"><?= moneyFmt($overview->totalExpense()) ?> €</td>
                <td class="whitespace-nowrap px-4 py-2 text-right <?= $overview->totalSaldo() >= 0 ? 'text-slate-800 dark:text-slate-100' : 'text-red-700 dark:text-red-400' ?>"><?= moneyFmt($overview->totalSaldo()) ?> €</td>
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
