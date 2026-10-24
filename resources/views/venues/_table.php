<?php
/** @var \App\Domain\Venue\Venue[] $venues */
/** @var array<string, bool> $needsDistributorFlags */
/** @var \App\Domain\Warehouse\Warehouse[] $warehouses */
/** @var string $companyAddress */
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
    <?php if ($venues === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('venues.table.empty')) ?></p>
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
                    <th class="break-words px-4 py-2.5"><?= e(t('venues.table.name')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('venues.table.address')) ?></th>
                    <th class="break-words px-4 py-2.5"><?= e(t('venues.table.map')) ?></th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($venues as $venue): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            <?= e($venue->name) ?>
                            <?php if ($needsDistributorFlags[$venue->id] ?? false): ?>
                                <span title="<?= e(t('venues.table.distributor_warning')) ?>" class="ml-1 cursor-help text-amber-600 dark:text-amber-400">&#9888;</span>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= e($venue->street) ?>
                            <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e($venue->postalCode . ' ' . $venue->city) ?></span>
                        </td>
                        <td class="break-words px-4 py-3">
                            <?php if ($venue->hasCoordinates()): ?>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700">
                                    <?= e(t('venues.table.coordinates_present')) ?>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">
                                    <?= e(t('venues.table.no_coordinates')) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-right">
                            <a href="/veranstaltungsorte/<?= e($venue->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                            <details class="relative mt-1 inline-block" data-dropdown>
                                <summary class="cursor-pointer list-none text-xs font-medium text-slate-600 hover:underline dark:text-slate-400"><?= e(t('common.route')) ?></summary>
                                <div data-dropdown-menu class="absolute right-0 z-10 mt-1 w-56 rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                    <?php if ($companyAddress !== ''): ?>
                                        <a href="https://www.google.com/maps/dir/?api=1&origin=<?= urlencode($companyAddress) ?>&destination=<?= urlencode($venue->fullAddress()) ?>&travelmode=driving" target="_blank" rel="noopener" class="block px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('rentals.logistics.route_from_company')) ?></a>
                                    <?php endif; ?>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <a href="https://www.google.com/maps/dir/?api=1&origin=<?= urlencode($warehouse->fullAddress()) ?>&destination=<?= urlencode($venue->fullAddress()) ?>&travelmode=driving" target="_blank" rel="noopener" class="block px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e($warehouse->name) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                            <form method="post" action="/veranstaltungsorte/<?= e($venue->id) ?>/loeschen" data-confirm="<?= e(t('venues.confirm_delete')) ?>" data-confirm-style="danger" class="mt-1">
                              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
