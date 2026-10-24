<?php
/**
 * Wiederverwendbare Paginierungs-Leiste für alle Listen-/Übersichtsseiten
 * (Artikel, Veranstaltungen, Kunden, ...). Wird aus der jeweiligen
 * "_table.php" eingebunden (require, kein View::render — die Variablen
 * werden dadurch aus dem aufrufenden Scope übernommen, siehe View::render()
 * in den einzelnen _table.php-Dateien).
 *
 * Erwartete Variablen:
 * @var int $page Aktuelle Seite (1-indexiert).
 * @var int $pageSize Aktuelle Seitengröße.
 * @var int $totalCount Gesamtanzahl der Treffer (mit aktiven Filtern).
 * @var int $totalPages Gesamtanzahl der Seiten (siehe PaginatedResult::totalPages()).
 * @var string $paginationBaseUrl Pfad der Liste, z. B. "/artikel".
 * @var array<string, string> $paginationQuery Aktive Filterparameter ohne "page"/"pageSize" (für Links im Nicht-JS-Fall).
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$buildUrl = static function (int $targetPage) use ($paginationBaseUrl, $paginationQuery, $pageSize): string {
    $params = $paginationQuery;
    $params['page'] = (string) $targetPage;
    $params['pageSize'] = (string) $pageSize;

    return $paginationBaseUrl . '?' . http_build_query($params);
};

// Zeigt maximal 5 Seitenzahlen um die aktuelle Seite herum, damit die Leiste
// bei vielen Seiten nicht unbegrenzt breit wird.
$windowStart = max(1, $page - 2);
$windowEnd = min($totalPages, $windowStart + 4);
$windowStart = max(1, $windowEnd - 4);
?>
<div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-800" data-pagination>
    <div class="flex items-center gap-3">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            <?= $totalCount ?> <?= $totalCount === 1 ? e(t('pagination.entry_singular')) : e(t('pagination.entry_plural')) ?> &middot; <?= e(t('pagination.page_of', ['page' => (string) $page, 'totalPages' => (string) $totalPages])) ?>
        </p>
        <label class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
            <span><?= e(t('pagination.per_page')) ?></span>
            <select
                data-page-size-select
                class="rounded-md border border-slate-300 px-2 py-1 text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900"
            >
                <?php foreach (\App\Infrastructure\Http\Pagination::ALLOWED_PAGE_SIZES as $option): ?>
                    <option value="<?= $option ?>" <?= $pageSize === $option ? 'selected' : '' ?>><?= $option ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <nav class="flex items-center gap-1" aria-label="<?= e(t('pagination.nav_label')) ?>">
        <a
            href="<?= e($buildUrl(max(1, $page - 1))) ?>"
            data-page-link
            data-page="<?= max(1, $page - 1) ?>"
            class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 <?= $page <= 1 ? 'pointer-events-none opacity-40' : '' ?>"
        ><?= e(t('pagination.previous')) ?></a>

        <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
            <a
                href="<?= e($buildUrl($p)) ?>"
                data-page-link
                data-page="<?= $p ?>"
                class="rounded-md border px-2.5 py-1.5 text-xs font-medium <?= $p === $page ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
            ><?= $p ?></a>
        <?php endfor; ?>

        <a
            href="<?= e($buildUrl(min($totalPages, $page + 1))) ?>"
            data-page-link
            data-page="<?= min($totalPages, $page + 1) ?>"
            class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 <?= $page >= $totalPages ? 'pointer-events-none opacity-40' : '' ?>"
        ><?= e(t('pagination.next')) ?></a>
    </nav>
</div>
