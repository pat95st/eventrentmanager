<?php
/** @var \App\Application\Purchase\Query\PurchaseOrderListEntry[] $orders */
/** @var string $search */
/** @var \App\Domain\Purchase\PurchaseOrderStatus|null $selectedStatus */
/** @var \App\Domain\Purchase\PurchaseOrderStatus[] $statuses */
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
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('purchase_orders.title')) ?></h1>
    <a href="/bestellungen/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        + <?= e(t('purchase_orders.new')) ?>
    </a>
</div>

<form method="get" action="/bestellungen" id="purchase-orders-filter-form" class="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('purchase_orders.filter.search_label')) ?></label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('purchase_orders.filter.search_placeholder')) ?>"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('purchase_orders.filter.status_label')) ?></label>
        <select name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value=""><?= e(t('purchase_orders.filter.all')) ?></option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status->value) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>>
                    <?= e($status->label()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-end gap-2">
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('purchase_orders.filter.submit')) ?>
        </button>
        <a href="/bestellungen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('purchase_orders.filter.reset')) ?></a>
    </div>
</form>

<div id="purchase-orders-table-container">
    <?php require __DIR__ . '/_table.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initPaginatedList({
            container: '#purchase-orders-table-container',
            form: '#purchase-orders-filter-form',
            endpoint: '/bestellungen',
        });
    });
</script>
