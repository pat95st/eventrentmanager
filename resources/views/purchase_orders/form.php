<?php
/** @var \App\Domain\Purchase\PurchaseOrder|null $order */
/** @var \App\Domain\Supplier\Supplier[] $suppliers */
/** @var \App\Domain\Article\Article[] $articles */
/** @var array<string, string> $articleDisplayNames Artikel-ID => "Hersteller Artikelbezeichnung" */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var array<int, array{article_id: string, target_type: string, quantity: int|string, unit_price: string}> $existingItems */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
$items = $old['items'] ?? $existingItems;
?>

<div class="mb-6">
    <a href="<?= $order !== null ? '/bestellungen/' . e($order->id) : '/bestellungen' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('purchase_orders.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $order !== null ? e(t('purchase_orders.edit')) : e(t('purchase_orders.new')) ?></h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/bestellungen/' . e($editingId) : '/bestellungen' ?>"
    class="max-w-5xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <label for="supplier_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('purchase_orders.form.supplier')) ?></label>
        <select id="supplier_id" name="supplier_id"
                class="mt-1 w-full rounded-md border <?= isset($errors['supplierId']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <option value=""><?= e(t('purchase_orders.form.supplier_placeholder')) ?></option>
            <?php $selectedSupplier = $val('supplier_id', $order?->supplierId); ?>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?= e($supplier->id) ?>" <?= $selectedSupplier === $supplier->id ? 'selected' : '' ?>><?= e($supplier->name) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['supplierId'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['supplierId']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="note" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('purchase_orders.form.note')) ?></label>
        <textarea id="note" name="note" rows="3"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"><?= e($val('note', $order?->note)) ?></textarea>
    </div>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('purchase_orders.form.items_title')) ?></h2>
            <button type="button" id="purchase-order-item-add" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                + <?= e(t('purchase_orders.form.items_add')) ?>
            </button>
        </div>
        <?php if (isset($errors['items'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['items']) ?></p><?php endif; ?>
        <div id="purchase-order-items" class="space-y-2"></div>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="<?= $order !== null ? '/bestellungen/' . e($order->id) : '/bestellungen' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<template id="purchase-order-item-row-template">
    <div class="purchase-order-item-row flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <div class="min-w-[180px] flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('purchase_orders.form.item_article')) ?></label>
            <select name="article_id[]" class="purchase-order-item-article mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                <option value=""><?= e(t('offers.show.article_placeholder')) ?></option>
                <?php foreach ($articles as $article): ?>
                    <option value="<?= e($article->id) ?>"><?= e($articleDisplayNames[$article->id] ?? $article->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-[180px]">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('purchase_orders.form.item_target')) ?></label>
            <select name="target_type[]" class="purchase-order-item-target mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                <option value="rental_pool"><?= e(t('purchase_orders.form.item_target_rental_pool')) ?></option>
                <option value="sale_stock"><?= e(t('purchase_orders.form.item_target_sale_stock')) ?></option>
            </select>
        </div>
        <div class="w-24">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('purchase_orders.form.item_quantity')) ?></label>
            <input type="number" name="quantity[]" min="1" value="1" class="purchase-order-item-quantity mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <div class="w-28">
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('purchase_orders.form.item_price')) ?></label>
            <input type="text" name="unit_price[]" inputmode="decimal" class="purchase-order-item-price mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <button type="button" class="purchase-order-item-remove rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
            <?= e(t('offers.form.item_remove')) ?>
        </button>
    </div>
</template>

<script>
(function () {
    var EXISTING_ITEMS = <?= json_encode(array_values($items), JSON_THROW_ON_ERROR) ?>;

    var container = document.getElementById('purchase-order-items');
    var template = document.getElementById('purchase-order-item-row-template');
    var addButton = document.getElementById('purchase-order-item-add');

    function addRow(data) {
        var fragment = template.content.cloneNode(true);
        var row = fragment.querySelector('.purchase-order-item-row');
        var articleSelect = row.querySelector('.purchase-order-item-article');
        var targetSelect = row.querySelector('.purchase-order-item-target');
        var quantityInput = row.querySelector('.purchase-order-item-quantity');
        var priceInput = row.querySelector('.purchase-order-item-price');
        var removeButton = row.querySelector('.purchase-order-item-remove');

        data = data || {};
        if (data.article_id) { articleSelect.value = data.article_id; }
        if (data.target_type) { targetSelect.value = data.target_type; }
        if (data.quantity) { quantityInput.value = data.quantity; }
        if (data.unit_price !== undefined) { priceInput.value = data.unit_price; }

        removeButton.addEventListener('click', function () { row.remove(); });

        container.appendChild(row);
    }

    addButton.addEventListener('click', function () { addRow(); });

    if (EXISTING_ITEMS.length > 0) {
        EXISTING_ITEMS.forEach(function (item) { addRow(item); });
    } else {
        addRow();
    }
})();
</script>
