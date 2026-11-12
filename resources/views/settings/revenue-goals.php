<?php
/** @var \App\Domain\RevenueGoal\RevenueGoal[] $goals */
/** @var int $year */
/** @var int $currentYear */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use App\Domain\RevenueGoal\RevenueGoalKind;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$money = static fn (string $value): string => number_format((float) $value, 2, ',', '.');
$inputClass = static fn (bool $error): string => 'w-full rounded-md border ' . ($error ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700') . ' px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900';
$kindLabels = [
    RevenueGoalKind::Target->value => t('settings.revenue_goals.kind.target'),
    RevenueGoalKind::Limit->value => t('settings.revenue_goals.kind.limit'),
];

// Zeilen des Formulars: bestehende Ziele, danach eine leere Zeile für ein neues Ziel.
$rows = [];
foreach ($goals as $goal) {
    $rows[] = [
        'key' => $goal->id,
        'field' => 'goals[' . $goal->id . ']',
        'goal' => $goal,
        'values' => $old['goals'][$goal->id] ?? [
            'name' => $goal->name,
            'kind' => $goal->kind->value,
            'amount' => $money($goal->amount),
            'year_amount' => $goal->hasOverrideFor($year) ? $money($goal->amountFor($year)) : '',
        ],
    ];
}
$rows[] = [
    'key' => 'new',
    'field' => 'new',
    'goal' => null,
    'values' => $old['new'] ?? ['name' => '', 'kind' => RevenueGoalKind::Target->value, 'amount' => '', 'year_amount' => ''],
];
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.revenue_goals.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.revenue_goals.subtitle')) ?></p>
    </div>

    <form method="get" action="/einstellungen/umsatzziele">
        <label for="jahr" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.revenue_goals.year_label')) ?></label>
        <select id="jahr" name="jahr" onchange="this.form.submit()" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
            <?php for ($y = max($currentYear + 3, $year); $y >= min($currentYear - 6, $year); $y--): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <noscript><button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700"><?= e(t('reporting.income_expense.apply')) ?></button></noscript>
    </form>
</div>

<div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
    <?= e(t('settings.revenue_goals.hint', ['year' => (string) $year])) ?>
    <a href="/berichte/einnahmen-ausgaben?jahr=<?= $year ?>" class="text-brand-700 underline dark:text-brand-400"><?= e(t('settings.revenue_goals.report_link', ['year' => (string) $year])) ?></a>
</div>

<form id="revenue-goals-form" method="post" action="/einstellungen/umsatzziele" class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <input type="hidden" name="jahr" value="<?= $year ?>">

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                    <th class="px-3 py-2 text-left"><?= e(t('settings.revenue_goals.field.name')) ?></th>
                    <th class="px-3 py-2 text-left"><?= e(t('settings.revenue_goals.field.kind')) ?></th>
                    <th class="px-3 py-2 text-left"><?= e(t('settings.revenue_goals.field.amount')) ?></th>
                    <th class="px-3 py-2 text-left"><?= e(t('settings.revenue_goals.field.year_amount', ['year' => (string) $year])) ?></th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $key = $row['key'];
                    $values = $row['values'];
                    $goal = $row['goal'];
                    $rowErrors = array_filter([$errors[$key . '.name'] ?? null, $errors[$key . '.amount'] ?? null, $errors[$key . '.year_amount'] ?? null]);
                    ?>
                    <?php if ($goal === null): ?>
                        <tr class="bg-slate-50 dark:bg-slate-800/50">
                            <td colspan="5" class="px-3 pt-3 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('settings.revenue_goals.new')) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="align-top">
                        <td class="px-3 py-3">
                            <input type="text" name="<?= e($row['field']) ?>[name]" value="<?= e((string) ($values['name'] ?? '')) ?>" maxlength="100"
                                   aria-label="<?= e(t('settings.revenue_goals.field.name')) ?>"
                                   placeholder="<?= $goal === null ? e(t('settings.revenue_goals.field.name_placeholder')) : '' ?>"
                                   class="<?= $inputClass(isset($errors[$key . '.name'])) ?>" style="min-width: 10rem">
                        </td>
                        <td class="px-3 py-3">
                            <select name="<?= e($row['field']) ?>[kind]" aria-label="<?= e(t('settings.revenue_goals.field.kind')) ?>" class="<?= $inputClass(false) ?>" style="min-width: 10rem">
                                <?php foreach ($kindLabels as $kindValue => $kindLabel): ?>
                                    <option value="<?= e($kindValue) ?>" <?= ($values['kind'] ?? '') === $kindValue ? 'selected' : '' ?>><?= e($kindLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-1" style="min-width: 7rem">
                                <input type="text" inputmode="decimal" name="<?= e($row['field']) ?>[amount]" value="<?= e((string) ($values['amount'] ?? '')) ?>"
                                       aria-label="<?= e(t('settings.revenue_goals.field.amount')) ?>"
                                       class="<?= $inputClass(isset($errors[$key . '.amount'])) ?> text-right tabular-nums">
                                <span class="text-sm text-slate-500 dark:text-slate-400">€</span>
                            </div>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-1" style="min-width: 7rem">
                                <input type="text" inputmode="decimal" name="<?= e($row['field']) ?>[year_amount]" value="<?= e((string) ($values['year_amount'] ?? '')) ?>"
                                       aria-label="<?= e(t('settings.revenue_goals.field.year_amount', ['year' => (string) $year])) ?>"
                                       placeholder="<?= e(t('settings.revenue_goals.field.year_amount_placeholder')) ?>"
                                       class="<?= $inputClass(isset($errors[$key . '.year_amount'])) ?> text-right tabular-nums">
                                <span class="text-sm text-slate-500 dark:text-slate-400">€</span>
                            </div>
                            <?php if ($goal !== null): ?>
                                <?php $otherYears = array_filter($goal->yearAmounts, static fn (int $y) => $y !== $year, ARRAY_FILTER_USE_KEY); ?>
                                <?php if ($otherYears !== []): ?>
                                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                        <?= e(t('settings.revenue_goals.other_years')) ?>
                                        <?php foreach ($otherYears as $otherYear => $otherAmount): ?>
                                            <a href="/einstellungen/umsatzziele?jahr=<?= (int) $otherYear ?>" class="whitespace-nowrap text-brand-700 hover:underline dark:text-brand-400"><?= (int) $otherYear ?>: <?= e($money($otherAmount)) ?> €</a>
                                        <?php endforeach; ?>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 text-right">
                            <?php if ($goal !== null): ?>
                                <button type="submit" form="delete-goal-<?= e($goal->id) ?>" class="whitespace-nowrap rounded-md px-2 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($rowErrors !== []): ?>
                        <tr>
                            <td colspan="5" class="px-3 py-2 text-xs text-red-600 dark:text-red-400"><?= e(implode(' ', array_unique($rowErrors))) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions flex items-center gap-3 border-t border-slate-100 p-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
    </div>
</form>

<?php foreach ($goals as $goal): ?>
    <form id="delete-goal-<?= e($goal->id) ?>" method="post" action="/einstellungen/umsatzziele/<?= e($goal->id) ?>/loeschen"
          onsubmit="return confirm(<?= e(json_encode(t('settings.revenue_goals.delete_confirm', ['name' => $goal->name]), JSON_UNESCAPED_UNICODE)) ?>)">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <input type="hidden" name="jahr" value="<?= $year ?>">
    </form>
<?php endforeach; ?>
