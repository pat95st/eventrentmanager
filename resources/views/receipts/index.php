<?php
/** @var \App\Application\Receipt\Query\ReceiptListEntry[] $receipts */
/** @var \App\Domain\Supplier\Supplier[] $suppliers */
/** @var string $search */
/** @var string $selectedSupplierId */
/** @var \App\Domain\Receipt\ReceiptStatus|null $selectedStatus */
/** @var \App\Domain\Receipt\ReceiptStatus[] $statuses */
/** @var \App\Domain\Receipt\ReceiptCategory|null $selectedCategory */
/** @var \App\Domain\Receipt\ReceiptCategory[] $categories */
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
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('receipts.title')) ?></h1>
    <div class="flex items-center gap-3">
        <a href="/belege/posteingang" class="relative rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('receipts.inbox.tab_label')) ?>
            <?php if (\App\Infrastructure\Http\ReceiptInboxBadge::pendingCount() > 0): ?>
                <span class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-red-500"></span>
            <?php endif; ?>
        </a>
        <a href="/belege/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            + <?= e(t('receipts.new')) ?>
        </a>
    </div>
</div>

<form method="get" action="/belege" id="receipts-filter-form" class="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-5 dark:border-slate-800 dark:bg-slate-900">
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.filter.search_label')) ?></label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('receipts.filter.search_placeholder')) ?>"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.filter.supplier_label')) ?></label>
        <select name="supplier_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value=""><?= e(t('receipts.filter.all')) ?></option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?= e($supplier->id) ?>" <?= $selectedSupplierId === $supplier->id ? 'selected' : '' ?>><?= e($supplier->name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.filter.status_label')) ?></label>
        <select name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value=""><?= e(t('receipts.filter.all')) ?></option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status->value) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>>
                    <?= e($status->label()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.filter.category_label')) ?></label>
        <select name="category" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value=""><?= e(t('receipts.filter.all')) ?></option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category->value) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>>
                    <?= e($category->label()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-end gap-2 sm:col-span-5">
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('receipts.filter.submit')) ?>
        </button>
        <a href="/belege" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('receipts.filter.reset')) ?></a>
    </div>
</form>

<div id="receipts-table-container">
    <?php require __DIR__ . '/_table.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initPaginatedList({
            container: '#receipts-table-container',
            form: '#receipts-filter-form',
            endpoint: '/belege',
        });
    });
</script>
