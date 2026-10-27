<?php
/** @var \App\Application\OrderConfirmation\Query\OrderConfirmationListEntry[] $orderConfirmations */
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
    <?php if ($orderConfirmations === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('order_confirmations.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 14%">
                <col style="width: 23%">
                <col style="width: 23%">
                <col style="width: 23%">
                <col style="width: 17%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5"><?= e(t('order_confirmations.table.number')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('order_confirmations.table.customer')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('order_confirmations.table.location')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('order_confirmations.table.period')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('order_confirmations.table.status')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($orderConfirmations as $entry): ?>
                    <?php $orderConfirmation = $entry->orderConfirmation; ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/auftragsbestaetigungen/<?= e($orderConfirmation->id) ?>'">
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= e($orderConfirmation->orderConfirmationNumber) ?></td>
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            <?= $entry->customer !== null ? e($entry->customer->displayName()) : e(t('order_confirmations.table.no_customer')) ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= $orderConfirmation->eventLocation !== null ? e($orderConfirmation->eventLocation) : '–' ?></td>
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= $orderConfirmation->startDate !== null && $orderConfirmation->endDate !== null
                                ? e($orderConfirmation->startDate->format('d.m.Y')) . ' – ' . e($orderConfirmation->endDate->format('d.m.Y'))
                                : '–' ?>
                        </td>
                        <td class="break-words px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($orderConfirmation->status->badgeClasses()) ?>"><?= e($orderConfirmation->status->label()) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
