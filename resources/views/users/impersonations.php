<?php
/** @var \App\Application\Auth\Query\ImpersonationListRow[] $rows */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Impersonations-Protokoll</h1>
    <a href="/benutzer" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
        Zurück zu Benutzern
    </a>
</div>

<div id="impersonations-table-container">
    <?php require __DIR__ . '/impersonations-table.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initPaginatedList({
            container: '#impersonations-table-container',
            endpoint: '/benutzer/impersonationen',
        });
    });
</script>
