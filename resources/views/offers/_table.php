<?php
/** @var \App\Application\Offer\Query\OfferListEntry[] $offers */
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
    <?php if ($offers === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('offers.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 24%">
                <col style="width: 22%">
                <col style="width: 22%">
                <col style="width: 16%">
                <col style="width: 16%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5"><?= e(t('offers.table.customer')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('offers.table.location')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('offers.table.period')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('offers.table.status')) ?></th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($offers as $entry): ?>
                    <?php $offer = $entry->offer; ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/angebote/<?= e($offer->id) ?>'">
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            <?= $entry->customer !== null ? e($entry->customer->displayName()) : e(t('offers.table.no_customer')) ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= $offer->eventLocation !== null ? e($offer->eventLocation) : '–' ?></td>
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= e($offer->startDate->format('d.m.Y')) ?> – <?= e($offer->endDate->format('d.m.Y')) ?>
                        </td>
                        <td class="break-words px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($offer->status->badgeClasses()) ?>"><?= e($offer->status->label()) ?></span>
                            <?php if ($offer->isExpiredForDisplay()): ?>
                                <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-400"><?= e(t('offers.badge.expired')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-right">
                            <?php if ($offer->convertedRentalId !== null): ?>
                                <span class="text-xs font-medium text-green-700 dark:text-green-400"><?= e(t('offers.table.converted')) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
