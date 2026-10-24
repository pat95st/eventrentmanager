<?php
/** @var \App\Domain\Warehouse\Warehouse|null $warehouse */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
?>

<div class="mb-6">
    <a href="/lagerorte" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('warehouses.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $warehouse !== null ? e(t('warehouses.edit')) : e(t('warehouses.new')) ?></h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/lagerorte/' . e($editingId) : '/lagerorte' ?>"
    class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('warehouses.form.name')) ?></label>
        <input type="text" id="name" name="name" required
               value="<?= e($val('name', $warehouse?->name)) ?>"
               placeholder="z. B. Hauptlager"
               class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="street" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('warehouses.form.street')) ?></label>
        <input type="text" id="street" name="street" required
               value="<?= e($val('street', $warehouse?->street)) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['street']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['street'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['street']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="postal_code" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('warehouses.form.postal_code')) ?></label>
            <input type="text" id="postal_code" name="postal_code" required
                   value="<?= e($val('postal_code', $warehouse?->postalCode)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['postalCode']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['postalCode'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['postalCode']) ?></p><?php endif; ?>
        </div>
        <div class="col-span-2">
            <label for="city" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('warehouses.form.city')) ?></label>
            <input type="text" id="city" name="city" required
                   value="<?= e($val('city', $warehouse?->city)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['city']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['city'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['city']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="country" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('warehouses.form.country')) ?></label>
        <input type="text" id="country" name="country"
               value="<?= e($val('country', $warehouse?->country)) ?>"
               placeholder="Deutschland"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('warehouses.form.country_hint')) ?></p>
    </div>

    <div>
        <label for="notes" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('warehouses.form.notes')) ?></label>
        <textarea id="notes" name="notes" rows="3"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"
        ><?= e($val('notes', $warehouse?->notes)) ?></textarea>
    </div>

    <div class="rounded-md bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400">
        <?= e(t('warehouses.form.coordinates_hint')) ?>
        <?php if ($warehouse !== null): ?>
            <br><?= e(t('warehouses.form.status_label')) ?>:
            <?php if ($warehouse->hasCoordinates()): ?>
                <span class="font-medium text-emerald-700 dark:text-emerald-400"><?= e(t('warehouses.form.coordinates_present')) ?></span>
            <?php else: ?>
                <span class="font-medium text-amber-700 dark:text-amber-400"><?= e(t('warehouses.form.coordinates_missing')) ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($warehouse !== null): ?>
        <div>
            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($warehouse->fullAddress()) ?>&travelmode=driving" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                <?= e(t('common.route')) ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="/lagerorte" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>
