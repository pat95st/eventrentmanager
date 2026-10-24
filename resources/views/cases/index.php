<?php
/** @var \App\Domain\CaseItem\CaseItem[] $cases */
/** @var \App\Domain\Manufacturer\Manufacturer[] $manufacturers */
/** @var string $search */
/** @var string|null $selectedManufacturerId */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Cases</h1>
    <a href="/cases/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        + Neues Case
    </a>
</div>

<form method="get" action="/cases" id="cases-filter-form" class="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="sm:col-span-3">
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Suche</label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Name oder Beschreibung"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Hersteller</label>
        <select name="hersteller" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value="">Alle</option>
            <?php foreach ($manufacturers as $manufacturer): ?>
                <option value="<?= e($manufacturer->id) ?>" <?= $selectedManufacturerId === $manufacturer->id ? 'selected' : '' ?>>
                    <?= e($manufacturer->name) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="sm:col-span-4">
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Filtern
        </button>
        <a href="/cases" class="ml-2 text-sm text-slate-500 hover:underline dark:text-slate-400">Zurücksetzen</a>
    </div>
</form>

<div id="cases-table-container">
    <?php require __DIR__ . '/_table.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initPaginatedList({
            container: '#cases-table-container',
            form: '#cases-filter-form',
            endpoint: '/cases',
        });
    });
</script>
