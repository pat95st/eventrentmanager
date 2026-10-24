<?php
/** @var \App\Application\Invoice\Query\InvoiceListEntry[] $invoices */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($invoices === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('invoices.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 16%">
                <col style="width: 24%">
                <col style="width: 20%">
                <col style="width: 18%">
                <col style="width: 12%">
                <col style="width: 10%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5"><?= e(t('invoices.table.number')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('invoices.table.customer')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('invoices.table.location')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('invoices.table.period')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('invoices.table.status')) ?></th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($invoices as $entry): ?>
                    <?php $invoice = $entry->invoice; ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/rechnungen/<?= e($invoice->id) ?>'">
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= $invoice->invoiceNumber !== null ? e($invoice->invoiceNumber) : e(t('invoices.table.no_number')) ?>
                        </td>
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            <?= $entry->customer !== null ? e($entry->customer->displayName()) : e(t('invoices.table.no_customer')) ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= $invoice->eventLocation !== null ? e($invoice->eventLocation) : '–' ?></td>
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= e($invoice->startDate->format('d.m.Y')) ?> – <?= e($invoice->endDate->format('d.m.Y')) ?>
                        </td>
                        <td class="break-words px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($invoice->status->badgeClasses()) ?>"><?= e($invoice->status->label()) ?></span>
                        </td>
                        <td class="break-words px-4 py-3 text-right"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
