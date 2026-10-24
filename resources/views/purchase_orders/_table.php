<?php
/** @var \App\Application\Purchase\Query\PurchaseOrderListEntry[] $orders */
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
    <?php if ($orders === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('purchase_orders.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[560px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 34%">
                <col style="width: 18%">
                <col style="width: 18%">
                <col style="width: 15%">
                <col style="width: 15%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5"><?= e(t('purchase_orders.table.supplier')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('purchase_orders.table.items')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('purchase_orders.table.status')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('purchase_orders.table.ordered_at')) ?></th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($orders as $entry): ?>
                    <?php $order = $entry->order; ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/bestellungen/<?= e($order->id) ?>'">
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            <?= $entry->supplier !== null ? e($entry->supplier->name) : e(t('purchase_orders.table.no_supplier')) ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= e((string) $entry->itemCount) ?></td>
                        <td class="break-words px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($order->status->badgeClasses()) ?>"><?= e($order->status->label()) ?></span>
                        </td>
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= $order->orderedAt !== null ? e($order->orderedAt->format('d.m.Y')) : '–' ?>
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
