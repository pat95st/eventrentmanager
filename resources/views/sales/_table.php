<?php
/** @var \App\Application\Sale\Query\SaleListEntry[] $sales */
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
    <?php if ($sales === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('sales.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 16%">
                <col style="width: 22%">
                <col style="width: 16%">
                <col style="width: 8%">
                <col style="width: 13%">
                <col style="width: 13%">
                <col style="width: 12%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5"><?= e(t('sales.table.sold_at')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('sales.table.article')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('sales.table.customer')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('sales.table.source')) ?></th>
                    <th class="break-words px-4 py-2.5 text-right"><?= e(t('sales.table.sale_price')) ?></th>
                    <th class="break-words px-4 py-2.5 text-right"><?= e(t('sales.table.margin')) ?></th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($sales as $entry): ?>
                    <?php $sale = $entry->sale; ?>
                    <?php $margin = $sale->marginAmount(); ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/rechnungen/<?= e($sale->invoiceId) ?>'">
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= e($sale->soldAt->format('d.m.Y')) ?></td>
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            <?= e($entry->displayName()) ?>
                            <?php if ($sale->quantity > 1): ?>
                                <span class="text-xs text-slate-400 dark:text-slate-500">(<?= $sale->quantity ?>×)</span>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= $entry->customer !== null ? e($entry->customer->displayName()) : e(t('sales.table.no_customer')) ?>
                        </td>
                        <td class="break-words px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                <?= $sale->isFromStock() ? e(t('sales.table.source_stock')) : e(t('sales.table.source_pool')) ?>
                            </span>
                        </td>
                        <td class="break-words px-4 py-3 text-right font-medium text-slate-800 dark:text-slate-200"><?= number_format((float) $sale->salePrice, 2, ',', '.') ?> €</td>
                        <td class="break-words px-4 py-3 text-right <?= $margin !== null && $margin < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-600 dark:text-slate-400' ?>">
                            <?= $margin !== null ? number_format($margin, 2, ',', '.') . ' €' : '–' ?>
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
