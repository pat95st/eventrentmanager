<?php
/** @var \App\Domain\Receipt\Receipt|null $receipt */
/** @var \App\Domain\Supplier\Supplier[] $suppliers */
/** @var \App\Domain\Receipt\ReceiptCategory[] $categories */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
?>

<div class="mb-6">
    <a href="<?= $receipt !== null ? '/belege/' . e($receipt->id) : '/belege' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('receipts.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $receipt !== null ? e(t('receipts.edit')) : e(t('receipts.new')) ?></h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/belege/' . e($editingId) : '/belege' ?>"
    enctype="multipart/form-data"
    class="max-w-3xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
    id="receipt-form"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="supplier_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.supplier')) ?></label>
            <select id="supplier_id" name="supplier_id" required
                    class="mt-1 w-full rounded-md border <?= isset($errors['supplierId']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <option value=""><?= e(t('receipts.form.supplier_placeholder')) ?></option>
                <?php $selectedSupplier = $val('supplier_id', $receipt?->supplierId); ?>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= e($supplier->id) ?>" <?= $selectedSupplier === $supplier->id ? 'selected' : '' ?>><?= e($supplier->name) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['supplierId'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['supplierId']) ?></p><?php endif; ?>
        </div>

        <div>
            <label for="receipt_number" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.receipt_number')) ?></label>
            <input type="text" id="receipt_number" name="receipt_number"
                   value="<?= e($val('receipt_number', $receipt?->receiptNumber)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('receipts.form.receipt_number_hint')) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="amount" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.amount')) ?></label>
            <input type="text" id="amount" name="amount" inputmode="decimal" required
                   value="<?= e($val('amount', $receipt?->amount)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['amount']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['amount'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['amount']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="receipt_date" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.date')) ?></label>
            <input type="date" id="receipt_date" name="receipt_date" required
                   value="<?= e($val('receipt_date', $receipt?->receiptDate->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['receiptDate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['receiptDate'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['receiptDate']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="category" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.category')) ?></label>
        <select id="category" name="category"
                class="mt-1 w-full rounded-md border <?= isset($errors['category']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php $selectedCategory = $val('category', $receipt?->category->value); ?>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category->value) ?>" <?= $selectedCategory === $category->value ? 'selected' : '' ?>><?= e($category->label()) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['category']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="note" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.note')) ?></label>
        <textarea id="note" name="note" rows="3"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"><?= e($val('note', $receipt?->note)) ?></textarea>
    </div>

    <div>
        <label for="document" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.file')) ?></label>
        <?php if ($receipt !== null): ?>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                <a href="/belege/<?= e($receipt->id) ?>/download" target="_blank" class="text-brand-600 hover:underline dark:text-brand-400"><?= e($receipt->originalFilename) ?></a>
                <span class="text-xs text-slate-400 dark:text-slate-500">(<?= e($receipt->humanReadableSize()) ?>)</span>
            </p>
        <?php endif; ?>
        <input type="file" id="document" name="document" accept="application/pdf,image/jpeg,image/png" <?= $receipt === null ? 'required' : '' ?>
               class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-400 dark:file:bg-slate-800 dark:file:text-slate-300 dark:hover:file:bg-slate-700">
        <?php if (isset($errors['document'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['document']) ?></p><?php endif; ?>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
            <?= $receipt !== null ? e(t('receipts.form.file_replace_hint')) : e(t('receipts.form.file_hint')) ?>
        </p>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="<?= $receipt !== null ? '/belege/' . e($receipt->id) : '/belege' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>
