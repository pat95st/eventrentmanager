<?php
/** @var \App\Domain\Voucher\Voucher|null $voucher */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var int $defaultValidityDays */

use App\Domain\Voucher\VoucherDiscountType;
use function App\Infrastructure\Http\e;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
$selectedType = $val('discount_type', $voucher?->discountType->value) ?: VoucherDiscountType::Percentage->value;
?>

<div class="mb-6">
    <a href="/gutscheine" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $voucher !== null ? 'Gutschein bearbeiten' : 'Neuer Gutschein' ?></h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/gutscheine/' . e($editingId) : '/gutscheine' ?>"
    class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <?php if ($voucher !== null): ?>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Code</label>
            <p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"><?= e($voucher->code) ?></p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Der Code wurde beim Anlegen automatisch erzeugt und kann nicht mehr geändert werden.</p>
        </div>
    <?php else: ?>
        <div>
            <label for="code_prefix" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Präfix (optional)</label>
            <input type="text" id="code_prefix" name="code_prefix"
                   value="<?= e($val('code_prefix')) ?>" placeholder="z. B. SOMMER"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Optional — wird dem automatisch erzeugten Code vorangestellt, z. B. "SOMMER-A1B2C3D4E5".</p>
        </div>
    <?php endif; ?>

    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rabatt-Art *</label>
        <div class="mt-1 flex gap-4">
            <?php foreach (VoucherDiscountType::cases() as $type): ?>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="radio" name="discount_type" value="<?= e($type->value) ?>"
                           <?= $selectedType === $type->value ? 'checked' : '' ?>>
                    <?= e($type->label()) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if (isset($errors['discountType'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['discountType']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="discount_value" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rabatt-Wert *</label>
        <input type="text" id="discount_value" name="discount_value" required
               value="<?= e($val('discount_value', $voucher?->discountValue)) ?>" placeholder="z. B. 15 oder 25,00"
               class="mt-1 w-full rounded-md border <?= isset($errors['discountValue']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Je nach Rabatt-Art ein Prozentsatz (0–100) oder ein Betrag in EUR.</p>
        <?php if (isset($errors['discountValue'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['discountValue']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="valid_from" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Gültig ab<?= $voucher !== null ? ' *' : '' ?></label>
            <input type="date" id="valid_from" name="valid_from"
                   value="<?= e($val('valid_from', $voucher?->validFrom->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['validFrom']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if ($voucher === null): ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Leer gelassen: heute.</p>
            <?php endif; ?>
            <?php if (isset($errors['validFrom'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['validFrom']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="valid_until" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Gültig bis<?= $voucher !== null ? ' *' : '' ?></label>
            <input type="date" id="valid_until" name="valid_until"
                   value="<?= e($val('valid_until', $voucher?->validUntil->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['validUntil']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if ($voucher === null): ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Leer gelassen: <?= $defaultValidityDays ?> Tage nach "Gültig ab" (Standardwert, siehe Einstellungen).</p>
            <?php endif; ?>
            <?php if (isset($errors['validUntil'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['validUntil']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="notes" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notiz</label>
        <textarea id="notes" name="notes" rows="3"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"><?= e($val('notes', $voucher?->notes)) ?></textarea>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Optional — z. B. Anlass oder Empfänger des Gutscheins.</p>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Speichern
        </button>
        <a href="/gutscheine" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
    </div>
</form>
