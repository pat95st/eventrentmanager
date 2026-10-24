<?php
/** @var \App\Application\Offer\Query\OfferListEntry[] $offers */
/** @var string $search */
/** @var \App\Domain\Offer\OfferStatus|null $selectedStatus */
/** @var \App\Domain\Offer\OfferStatus[] $statuses */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('offers.title')) ?></h1>
    <a href="/angebote/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        + <?= e(t('offers.new')) ?>
    </a>
</div>

<form method="get" action="/angebote" id="offers-filter-form" class="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('offers.filter.search_label')) ?></label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('offers.filter.search_placeholder')) ?>"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('offers.filter.status_label')) ?></label>
        <select name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value=""><?= e(t('offers.filter.all')) ?></option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status->value) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>>
                    <?= e($status->label()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-end gap-2">
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('offers.filter.submit')) ?>
        </button>
        <a href="/angebote" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('offers.filter.reset')) ?></a>
    </div>
</form>

<div id="offers-table-container">
    <?php require __DIR__ . '/_table.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initPaginatedList({
            container: '#offers-table-container',
            form: '#offers-filter-form',
            endpoint: '/angebote',
        });
    });
</script>
