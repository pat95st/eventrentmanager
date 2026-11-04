<?php
/** @var string $importId */
/** @var \App\Domain\BankImport\BankStatementFormat $format */
/** @var \App\Application\BankImport\Command\BankImportRow[] $rows */
/** @var string[] $openInvoiceNumbers */
/** @var \App\Domain\Settings\BankAccount[] $bankAccounts */
/** @var string|null $defaultBankAccountId */
$bankAccounts ??= [];
$defaultBankAccountId ??= null;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('bank_import.review.title')) ?></h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e(t('bank_import.review.intro', ['format' => $format->label()])) ?></p>
</div>

<datalist id="open-invoice-numbers">
    <?php foreach ($openInvoiceNumbers as $invoiceNumber): ?>
        <option value="<?= e($invoiceNumber) ?>"></option>
    <?php endforeach; ?>
</datalist>

<form method="post" action="/zahlungsabgleich/bestaetigen" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <input type="hidden" name="import_id" value="<?= e($importId) ?>">

    <?php if ($bankAccounts !== [] && $rows !== []): /* Kontoführung (Migration 131) */ ?>
        <div class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div>
                <label for="ledger_account_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Umsätze in die Kontoführung übernehmen</label>
                <select id="ledger_account_id" name="ledger_account_id" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
                    <option value="">nicht übernehmen</option>
                    <?php foreach ($bankAccounts as $bankAccount): ?>
                        <option value="<?= e($bankAccount->id) ?>" <?= $bankAccount->id === $defaultBankAccountId ? 'selected' : '' ?>><?= e($bankAccount->label) ?> (<?= e($bankAccount->formattedIban()) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="max-w-md text-xs text-slate-500 dark:text-slate-400">Angehakt in der Spalte „Konto“. Bereits übernommene Umsätze werden beim erneuten Import übersprungen.</p>
        </div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <p class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
            <?= e(t('bank_import.review.empty')) ?>
        </p>
    <?php else: ?>
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2.5"></th>
                        <th class="px-3 py-2.5"><?= e(t('bank_import.review.table.date')) ?></th>
                        <th class="px-3 py-2.5"><?= e(t('bank_import.review.table.amount')) ?></th>
                        <th class="px-3 py-2.5"><?= e(t('bank_import.review.table.purpose')) ?></th>
                        <th class="px-3 py-2.5"><?= e(t('bank_import.review.table.counterparty')) ?></th>
                        <th class="px-3 py-2.5"><?= e(t('bank_import.review.table.invoice_number')) ?></th>
                        <th class="px-3 py-2.5"><?= e(t('bank_import.review.table.status')) ?></th>
                        <?php if ($bankAccounts !== []): ?><th class="px-3 py-2.5" title="In die Kontoführung übernehmen">Konto</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($rows as $row): ?>
                        <?php $defaultChecked = $row->isCredit && $row->matchedInvoiceNumber !== null; ?>
                        <tr class="align-top">
                            <td class="px-3 py-2.5">
                                <input type="checkbox" name="included[]" value="<?= e((string) $row->rowIndex) ?>" <?= $defaultChecked ? 'checked' : '' ?>>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-slate-700 dark:text-slate-300"><?= e($row->bookingDate) ?></td>
                            <td class="whitespace-nowrap px-3 py-2.5 <?= $row->isCredit ? 'text-green-700 dark:text-green-400' : 'text-slate-500 dark:text-slate-400' ?>">
                                <?= $row->isCredit ? '+' : '−' ?><?= e(number_format((float) $row->amount, 2, ',', '.')) ?> <?= e($row->currency) ?>
                            </td>
                            <td class="max-w-xs truncate px-3 py-2.5 text-slate-600 dark:text-slate-400" title="<?= e($row->purpose ?? '') ?>"><?= e($row->purpose ?? '—') ?></td>
                            <td class="px-3 py-2.5 text-slate-600 dark:text-slate-400"><?= e($row->counterpartyName ?? '—') ?></td>
                            <td class="px-3 py-2.5">
                                <input type="text" name="invoice_number[<?= e((string) $row->rowIndex) ?>]"
                                       value="<?= e($row->matchedInvoiceNumber ?? '') ?>"
                                       list="open-invoice-numbers"
                                       class="w-40 rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900">
                                <?php if ($row->amountMismatch): ?>
                                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400"><?= e(t('bank_import.review.amount_mismatch', ['invoice_amount' => number_format((float) $row->matchedGrossTotal, 2, ',', '.')])) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2.5">
                                <?php if (!$row->isCredit): ?>
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400"><?= e(t('bank_import.review.status.debit')) ?></span>
                                <?php elseif ($row->matchedInvoiceNumber !== null): ?>
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700 dark:bg-green-950/40 dark:text-green-400"><?= e($row->matchedCustomerName ?? t('bank_import.review.status.matched')) ?></span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-950/40 dark:text-amber-400"><?= e(t('bank_import.review.status.no_match')) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($bankAccounts !== []): ?>
                                <td class="px-3 py-2.5 text-center"><input type="checkbox" name="ledger[]" value="<?= e((string) $row->rowIndex) ?>" checked aria-label="In die Kontoführung übernehmen"></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="inline-flex items-center rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('bank_import.review.confirm')) ?>
        </button>
        <button type="submit" form="bank-import-cancel-form" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('bank_import.review.cancel')) ?>
        </button>
    </div>
</form>

<form id="bank-import-cancel-form" method="post" action="/zahlungsabgleich/abbrechen" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <input type="hidden" name="import_id" value="<?= e($importId) ?>">
</form>
