<?php
/** @var \App\Domain\Supplier\Supplier|null $supplier */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */

use App\Domain\Supplier\SupplierType;
use function App\Infrastructure\Http\e;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
$selectedType = $val('type', $supplier?->type->value) ?: SupplierType::Kauf->value;
?>

<div class="mb-6">
    <a href="/lieferanten" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $supplier !== null ? 'Lieferant bearbeiten' : 'Neuer Lieferant' ?></h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/lieferanten/' . e($editingId) : '/lieferanten' ?>"
    class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name *</label>
        <input type="text" id="name" name="name" required
               value="<?= e($val('name', $supplier?->name)) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Art *</label>
        <div class="mt-1 flex gap-4">
            <?php foreach (SupplierType::cases() as $type): ?>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="radio" name="type" value="<?= e($type->value) ?>"
                           <?= $selectedType === $type->value ? 'checked' : '' ?>>
                    <?= e($type->label()) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if (isset($errors['type'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['type']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="street" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Straße/Hausnummer *</label>
        <input type="text" id="street" name="street" required
               value="<?= e($val('street', $supplier?->street)) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['street']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['street'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['street']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="postal_code" class="block text-xs font-medium text-slate-600 dark:text-slate-400">PLZ *</label>
            <input type="text" id="postal_code" name="postal_code" required
                   value="<?= e($val('postal_code', $supplier?->postalCode)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['postalCode']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['postalCode'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['postalCode']) ?></p><?php endif; ?>
        </div>
        <div class="col-span-2">
            <label for="city" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ort *</label>
            <input type="text" id="city" name="city" required
                   value="<?= e($val('city', $supplier?->city)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['city']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['city'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['city']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="phone" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Telefon</label>
            <input type="text" id="phone" name="phone"
                   value="<?= e($val('phone', $supplier?->phone)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div>
            <label for="email" class="block text-xs font-medium text-slate-600 dark:text-slate-400">E-Mail-Adresse</label>
            <input type="email" id="email" name="email"
                   value="<?= e($val('email', $supplier?->email)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['email']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['email'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['email']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="customer_number" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unsere Kundennummer</label>
        <input type="text" id="customer_number" name="customer_number"
               value="<?= e($val('customer_number', $supplier?->customerNumber)) ?>"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Optional — unsere Kundennummer bei diesem Lieferanten.</p>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Speichern
        </button>
        <a href="/lieferanten" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
    </div>
</form>
