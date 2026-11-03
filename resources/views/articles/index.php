<?php
/** @var \App\Application\Article\Query\ArticleListItem[] $articles */
/** @var \App\Domain\Manufacturer\Manufacturer[] $manufacturers */
/** @var \App\Domain\Category\Category[] $categories */
/** @var string $search */
/** @var string|null $selectedManufacturerId */
/** @var string|null $selectedCategoryId */
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
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('articles.title')) ?></h1>
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" data-price-list-open class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('articles.price_list.export_pdf')) ?>
        </button>
        <a href="/artikel/preisliste.csv" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('articles.price_list.export_csv')) ?>
        </a>
        <a href="/artikel/neu?stromverteiler=1" class="rounded-md border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-slate-800">
            + Neuer Stromverteiler
        </a>
        <a href="/artikel/neues-bundle" class="rounded-md border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-slate-800">
            + <?= e(t('articles.bundle.new')) ?>
        </a>
        <a href="/artikel/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            + <?= e(t('articles.new')) ?>
        </a>
    </div>
</div>

<form method="get" action="/artikel" id="articles-filter-form" class="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.filter.search_label')) ?></label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('articles.filter.search_placeholder')) ?>"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.filter.manufacturer_label')) ?></label>
        <select name="hersteller" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value=""><?= e(t('articles.filter.all')) ?></option>
            <?php foreach ($manufacturers as $manufacturer): ?>
                <option value="<?= e($manufacturer->id) ?>" <?= $selectedManufacturerId === $manufacturer->id ? 'selected' : '' ?>>
                    <?= e($manufacturer->name) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.filter.category_label')) ?></label>
        <select name="kategorie" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value=""><?= e(t('articles.filter.all')) ?></option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category->id) ?>" <?= $selectedCategoryId === $category->id ? 'selected' : '' ?>>
                    <?= e($category->name) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="sm:col-span-4">
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('articles.filter.submit')) ?>
        </button>
        <a href="/artikel" class="ml-2 text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('articles.filter.reset')) ?></a>
    </div>
</form>

<div id="articles-table-container">
    <?php require __DIR__ . '/_table.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initPaginatedList({
            container: '#articles-table-container',
            form: '#articles-filter-form',
            endpoint: '/artikel',
        });
    });
</script>

<!-- Exporteinstellungen der PDF-Preisliste (Nutzerwunsch) -->
<div data-price-list-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <form method="get" action="/artikel/preisliste.pdf" target="_blank" class="modal-panel w-full max-w-md rounded-lg bg-white p-5 shadow-xl dark:bg-slate-900">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100"><?= e(t('articles.price_list.modal_title')) ?></h2>
        <fieldset class="mb-4">
            <legend class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('articles.price_list.sort_label')) ?></legend>
            <label class="flex items-start gap-2 py-1 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="sort" value="alpha" checked class="mt-1">
                <span><?= e(t('articles.price_list.sort_alpha')) ?></span>
            </label>
            <label class="flex items-start gap-2 py-1 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="sort" value="category" class="mt-1">
                <span><?= e(t('articles.price_list.sort_category')) ?></span>
            </label>
        </fieldset>
        <fieldset class="mb-5">
            <legend class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('articles.price_list.content_label')) ?></legend>
            <label class="flex items-start gap-2 py-1 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="images" value="1" class="mt-1">
                <span><?= e(t('articles.price_list.with_images')) ?></span>
            </label>
            <label class="flex items-start gap-2 py-1 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="short_texts" value="1" class="mt-1">
                <span><?= e(t('articles.price_list.with_short_texts')) ?></span>
            </label>
        </fieldset>
        <div class="flex items-center justify-end gap-3">
            <button type="button" data-price-list-close class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></button>
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('articles.price_list.export_button')) ?></button>
        </div>
    </form>
</div>
<script>
(function () {
    var modal = document.querySelector('[data-price-list-modal]');
    var openButton = document.querySelector('[data-price-list-open]');
    if (!modal || !openButton) { return; }
    var storageKey = 'priceListExport';
    var form = modal.querySelector('form');
    // Letzte Auswahl merken (nur Komfort, pro Browser).
    try {
        var saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
        if (saved) {
            form.querySelectorAll('input[name="sort"]').forEach(function (r) { r.checked = r.value === saved.sort; });
            form.elements.images.checked = !!saved.images;
            form.elements.short_texts.checked = !!saved.shortTexts;
        }
    } catch (e) {}
    function open() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    function close() { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    openButton.addEventListener('click', open);
    modal.querySelector('[data-price-list-close]').addEventListener('click', close);
    modal.addEventListener('click', function (event) { if (event.target === modal) { close(); } });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !modal.classList.contains('hidden')) { close(); } });
    form.addEventListener('submit', function () {
        try {
            localStorage.setItem(storageKey, JSON.stringify({ sort: (form.querySelector('input[name="sort"]:checked') || {}).value, images: form.elements.images.checked, shortTexts: form.elements.short_texts.checked }));
        } catch (e) {}
        setTimeout(close, 0);
    });
})();
</script>
