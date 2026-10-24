<?php
/** @var \App\Application\Receipt\Query\ReceiptListEntry[] $receipts */
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
    <?php if ($receipts === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('receipts.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 16%">
                <col style="width: 24%">
                <col style="width: 12%">
                <col style="width: 14%">
                <col style="width: 16%">
                <col style="width: 18%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5"><?= e(t('receipts.table.number')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('receipts.table.supplier')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('receipts.table.date')) ?></th>
                    <th class="break-words px-4 py-2.5 text-right"><?= e(t('receipts.table.amount')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('receipts.table.category')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('receipts.table.status')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($receipts as $entry): ?>
                    <?php $receipt = $entry->receipt; ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/belege/<?= e($receipt->id) ?>'">
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= $receipt->receiptNumber !== null ? e($receipt->receiptNumber) : '–' ?>
                        </td>
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            <?= $entry->supplier !== null ? e($entry->supplier->name) : e(t('receipts.table.no_supplier')) ?>
                        </td>
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= e($receipt->receiptDate->format('d.m.Y')) ?>
                        </td>
                        <td class="break-words px-4 py-3 text-right font-medium text-slate-800 dark:text-slate-200">
                            <?= number_format((float) $receipt->amount, 2, ',', '.') ?> €
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($receipt->category->label()) ?></td>
                        <td class="break-words px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($receipt->status->badgeClasses()) ?>"><?= e($receipt->status->label()) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
