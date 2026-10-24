<?php
/** @var \App\Domain\Warehouse\Warehouse[] $warehouses */
/** @var string $search */

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
    <?php if ($warehouses === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('warehouses.table.empty')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[560px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 28%">
                <col style="width: 36%">
                <col style="width: 18%">
                <col style="width: 18%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5"><?= e(t('warehouses.table.name')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('warehouses.table.address')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('warehouses.table.map')) ?></th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($warehouses as $index => $warehouse): ?>
                    <?php $openUpward = $index >= count($warehouses) - 2; ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            <?= e($warehouse->name) ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= e($warehouse->street) ?>
                            <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e($warehouse->postalCode . ' ' . $warehouse->city) ?></span>
                        </td>
                        <td class="break-words px-4 py-3">
                            <?php if ($warehouse->hasCoordinates()): ?>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700">
                                    <?= e(t('warehouses.table.coordinates_present')) ?>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">
                                    <?= e(t('warehouses.table.no_coordinates')) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-right">
                            <details class="relative inline-block text-left" data-dropdown>
                                <summary class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                    &#8942;
                                </summary>
                                <div data-dropdown-menu class="absolute right-0 z-10 <?= $openUpward ? 'bottom-full mb-1' : 'top-full mt-1' ?> w-40 rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                    <a href="/lagerorte/<?= e($warehouse->id) ?>/bearbeiten" class="block px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
                                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($warehouse->fullAddress()) ?>&travelmode=driving" target="_blank" rel="noopener" class="block px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.route')) ?></a>
                                    <form method="post" action="/lagerorte/<?= e($warehouse->id) ?>/loeschen" data-confirm="<?= e(t('warehouses.confirm_delete')) ?>" data-confirm-style="danger">
                                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <button type="submit" class="block w-full px-3 py-1.5 text-left text-xs text-red-600 hover:bg-slate-50 dark:text-red-400 dark:hover:bg-slate-800"><?= e(t('common.delete')) ?></button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
