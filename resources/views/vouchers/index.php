<?php
/** @var \App\Domain\Voucher\Voucher[] $vouchers */
/** @var array<string, \App\Domain\Voucher\VoucherRedemption> $redemptionsByVoucherId */
/** @var string $search */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Gutscheine</h1>
    <a href="/gutscheine/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        + Neuer Gutschein
    </a>
</div>

<form method="get" action="/gutscheine" id="vouchers-filter-form" class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex-1">
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Suche</label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Gutscheincode"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
    </div>
    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
        Filtern
    </button>
    <a href="/gutscheine" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Zurücksetzen</a>
</form>

<div id="vouchers-table-container">
    <?php require __DIR__ . '/_table.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initPaginatedList({
            container: '#vouchers-table-container',
            form: '#vouchers-filter-form',
            endpoint: '/gutscheine',
        });
    });
</script>
