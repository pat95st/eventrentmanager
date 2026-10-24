<?php
/** @var \App\Domain\ReceiptInbox\ReceiptInboxAttachment $attachment */
/** @var \App\Domain\Supplier\Supplier[] $suppliers */
/** @var \App\Domain\Receipt\ReceiptCategory[] $categories */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
?>

<div class="mb-6">
    <a href="/belege/posteingang" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('receipts.inbox.convert_back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('receipts.inbox.convert_title')) ?></h1>
</div>

<form
    method="post"
    action="/belege/posteingang/anhaenge/<?= e($attachment->id) ?>/beleg-erstellen"
    class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
        <?= e(t('receipts.inbox.convert_file_hint', ['filename' => $attachment->originalFilename])) ?>
        <a href="/belege/posteingang/anhaenge/<?= e($attachment->id) ?>/download" target="_blank" class="ml-1 text-brand-600 hover:underline dark:text-brand-400"><?= e(t('receipts.inbox.convert_file_preview')) ?></a>
    </div>

    <div>
        <label for="supplier_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.supplier')) ?></label>
        <select id="supplier_id" name="supplier_id" required
                class="mt-1 w-full rounded-md border <?= isset($errors['supplierId']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <option value=""><?= e(t('receipts.form.supplier_placeholder')) ?></option>
            <?php $selectedSupplier = $val('supplier_id'); ?>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?= e($supplier->id) ?>" <?= $selectedSupplier === $supplier->id ? 'selected' : '' ?>><?= e($supplier->name) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['supplierId'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['supplierId']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="receipt_number" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.receipt_number')) ?></label>
        <input type="text" id="receipt_number" name="receipt_number"
               value="<?= e($val('receipt_number')) ?>"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('receipts.form.receipt_number_hint')) ?></p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="amount" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.amount')) ?></label>
            <input type="text" id="amount" name="amount" inputmode="decimal" required
                   value="<?= e($val('amount')) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['amount']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['amount'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['amount']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="receipt_date" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.date')) ?></label>
            <input type="date" id="receipt_date" name="receipt_date" required
                   value="<?= e($val('receipt_date')) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['receiptDate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['receiptDate'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['receiptDate']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="category" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.category')) ?></label>
        <select id="category" name="category"
                class="mt-1 w-full rounded-md border <?= isset($errors['category']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php $selectedCategory = $val('category'); ?>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category->value) ?>" <?= $selectedCategory === $category->value ? 'selected' : '' ?>><?= e($category->label()) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['category']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="note" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('receipts.form.note')) ?></label>
        <textarea id="note" name="note" rows="3"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"><?= e($val('note')) ?></textarea>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('receipts.inbox.convert_submit')) ?>
        </button>
        <a href="/belege/posteingang" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>
