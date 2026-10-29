<?php
/** @var \App\Domain\Settings\PaymentMethod[] $methods */
/** @var \App\Domain\Settings\PaymentMethod|null $editing */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, ?string $fallback) => array_key_exists($key, $old) ? (string) $old[$key] : ($fallback ?? '');
$isDefaultChecked = array_key_exists('is_default', $old) ? $old['is_default'] === '1' : ($editing?->isDefault ?? $methods === []);
$inputClass = fn (string $errorKey) => 'mt-1 w-full rounded-md border ' . (isset($errors[$errorKey]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700') . ' px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500';
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.payment_methods.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.payment_methods.subtitle')) ?></p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <?php if ($methods === []): ?>
            <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.payment_methods.empty')) ?></p>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($methods as $method): ?>
                    <?php $isEditing = $editing !== null && $editing->id === $method->id; ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 px-2 py-2.5 <?= $isEditing ? 'bg-brand-50 rounded-md ring-1 ring-brand-200 dark:bg-brand-950/40 dark:ring-brand-800' : '' ?>">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                                <?= e($method->name) ?>
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e($method->termLabel()) ?></span>
                                <?php if ($method->isDefault): ?>
                                    <span class="rounded bg-brand-50 px-1.5 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-950/40 dark:text-brand-400"><?= e(t('settings.payment_methods.default_badge')) ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if ($method->noteText !== null): ?>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400"><?= e($method->noteText) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="flex items-center gap-3">
                            <a href="/einstellungen/zahlungsarten/<?= e($method->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                            <form method="post" action="/einstellungen/zahlungsarten/<?= e($method->id) ?>/loeschen" data-confirm="<?= e(t('settings.payment_methods.confirm_delete')) ?>" data-confirm-style="danger">
                                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                            </form>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
            <?= $editing !== null ? e(t('settings.payment_methods.form.title_edit')) : e(t('settings.payment_methods.form.title_create')) ?>
        </h2>
        <form method="post" action="<?= $editing !== null ? '/einstellungen/zahlungsarten/' . e($editing->id) : '/einstellungen/zahlungsarten' ?>" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.payment_methods.form.name')) ?> *</label>
                <input type="text" id="name" name="name" required placeholder="<?= e(t('settings.payment_methods.form.name_placeholder')) ?>"
                       value="<?= e($val('name', $editing?->name)) ?>" class="<?= $inputClass('name') ?>">
                <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="payment_term_days" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.payment_methods.form.payment_term_days')) ?> *</label>
                <input type="number" id="payment_term_days" name="payment_term_days" min="0" max="365" step="1" required
                       value="<?= e($val('payment_term_days', $editing !== null ? (string) $editing->paymentTermDays : '14')) ?>" class="<?= $inputClass('paymentTermDays') ?> max-w-[10rem]">
                <?php if (isset($errors['paymentTermDays'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['paymentTermDays']) ?></p><?php endif; ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.payment_methods.form.payment_term_days_hint')) ?></p>
            </div>
            <div>
                <label for="note_text" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.payment_methods.form.note_text')) ?></label>
                <textarea id="note_text" name="note_text" rows="3" class="<?= $inputClass('noteText') ?>"><?= e($val('note_text', $editing?->noteText)) ?></textarea>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.payment_methods.form.note_text_hint')) ?></p>
            </div>

            <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="is_default" value="1" <?= $isDefaultChecked ? 'checked' : '' ?> <?= $editing?->isDefault ? 'disabled' : '' ?>
                       class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                <span>
                    <?= e(t('settings.payment_methods.form.is_default')) ?>
                    <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e(t($editing?->isDefault ? 'settings.payment_methods.form.is_default_locked' : 'settings.payment_methods.form.is_default_hint')) ?></span>
                </span>
            </label>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
                <?php if ($editing !== null): ?>
                    <a href="/einstellungen/zahlungsarten" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
