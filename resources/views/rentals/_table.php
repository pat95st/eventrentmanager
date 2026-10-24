<?php
/** @var \App\Application\Rental\Query\RentalListEntry[] $rentals */
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
    <?php if ($rentals === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('rentals.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 24%">
                <col style="width: 20%">
                <col style="width: 18%">
                <col style="width: 18%">
                <col style="width: 12%">
                <col style="width: 8%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5"><?= e(t('rentals.table.event')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('rentals.table.customer')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('rentals.table.period')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('rentals.table.location')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('rentals.table.deployment_days')) ?></th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($rentals as $entry): ?>
                    <?php $rental = $entry->rental; ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/veranstaltungen/<?= e($rental->id) ?>'">
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100"><?= e($rental->eventName) ?></td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($entry->customer->displayName()) ?></td>
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <?= e($rental->startDate->format('d.m.Y')) ?> – <?= e($rental->endDate->format('d.m.Y')) ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= $rental->eventLocation !== null ? e($rental->eventLocation) : '–' ?></td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= $rental->deploymentDays ?></td>
                        <td class="break-words px-4 py-3 text-right">
                            <a href="/veranstaltungen/<?= e($rental->id) ?>/ausgabe" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400" onclick="event.stopPropagation()"><?= e(t('rentals.table.checkout_link')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>

