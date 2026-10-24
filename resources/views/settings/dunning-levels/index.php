<?php
/** @var \App\Domain\Dunning\DunningLevel[] $levels */
/** @var \App\Domain\Dunning\DunningLevel|null $editingLevel */
/** @var \App\Domain\Dunning\DunningSettings $dunningSettings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.dunning_levels.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.dunning_levels.subtitle')) ?></p>
</div>

<div class="mb-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.dunning_levels.auto_send.title')) ?></h2>
    <form method="post" action="/einstellungen/mahnwesen/einstellungen">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="auto_send_enabled" value="1" <?= $dunningSettings->autoSendEnabled ? 'checked' : '' ?>
                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
            <?= e(t('settings.dunning_levels.auto_send.field')) ?>
        </label>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.dunning_levels.auto_send.hint')) ?></p>
        <button type="submit" class="mt-4 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </form>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <?php if ($levels === []): ?>
            <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.dunning_levels.empty')) ?></p>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($levels as $level): ?>
                    <?php $isEditing = $editingLevel !== null && $editingLevel->id === $level->id; ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 px-2 py-2.5 <?= $isEditing ? 'bg-brand-50 rounded-md ring-1 ring-brand-200 dark:bg-brand-950/40 dark:ring-brand-800' : '' ?>">
                        <span class="flex flex-wrap items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                            <?= e($level->name) ?>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                <?= e(t('settings.dunning_levels.days_after_due_badge', ['days' => (string) $level->daysAfterDue])) ?>
                            </span>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                <?php if ($level->feeType === \App\Domain\Dunning\DunningFeeType::None): ?>
                                    <?= e($level->feeType->label()) ?>
                                <?php elseif ($level->feeType === \App\Domain\Dunning\DunningFeeType::Fixed): ?>
                                    <?= e(number_format((float) $level->feeValue, 2, ',', '.')) ?> €
                                <?php else: ?>
                                    <?= e(number_format((float) $level->feeValue, 2, ',', '.')) ?> %
                                <?php endif; ?>
                            </span>
                        </span>
                        <span class="flex items-center gap-3">
                            <a href="/einstellungen/mahnwesen/<?= e($level->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                            <form method="post" action="/einstellungen/mahnwesen/<?= e($level->id) ?>/loeschen" data-confirm="<?= e(t('settings.dunning_levels.confirm_delete')) ?>" data-confirm-style="danger">
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
            <?= $editingLevel !== null ? e(t('settings.dunning_levels.form.title_edit')) : e(t('settings.dunning_levels.form.title_create')) ?>
        </h2>
        <form
            method="post"
            action="<?= $editingLevel !== null ? '/einstellungen/mahnwesen/' . e($editingLevel->id) : '/einstellungen/mahnwesen' ?>"
            class="space-y-4"
        >
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.dunning_levels.form.name')) ?> *</label>
                <input
                    type="text" id="name" name="name" required
                    value="<?= e($old['name'] ?? $editingLevel?->name ?? '') ?>"
                    class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                <?php if (isset($errors['name'])): ?>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="days_after_due" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.dunning_levels.form.days_after_due')) ?> *</label>
                <input
                    type="number" id="days_after_due" name="days_after_due" min="0" required
                    value="<?= e($old['days_after_due'] ?? (string) ($editingLevel?->daysAfterDue ?? '')) ?>"
                    class="mt-1 w-full rounded-md border <?= isset($errors['daysAfterDue']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                <?php if (isset($errors['daysAfterDue'])): ?>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['daysAfterDue']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="fee_type" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.dunning_levels.form.fee_type')) ?></label>
                <?php $currentFeeType = $old['fee_type'] ?? $editingLevel?->feeType->value ?? \App\Domain\Dunning\DunningFeeType::None->value; ?>
                <select id="fee_type" name="fee_type" class="mt-1 w-full rounded-md border <?= isset($errors['feeType']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php foreach (\App\Domain\Dunning\DunningFeeType::cases() as $option): ?>
                        <option value="<?= e($option->value) ?>" <?= $currentFeeType === $option->value ? 'selected' : '' ?>><?= e($option->label()) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['feeType'])): ?>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['feeType']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="fee_value" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.dunning_levels.form.fee_value')) ?></label>
                <input
                    type="text" id="fee_value" name="fee_value" inputmode="decimal"
                    placeholder="z. B. 5,00"
                    value="<?= e($old['fee_value'] ?? $editingLevel?->feeValue ?? '') ?>"
                    class="mt-1 w-full rounded-md border <?= isset($errors['feeValue']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                <?php if (isset($errors['feeValue'])): ?>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['feeValue']) ?></p>
                <?php endif; ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.dunning_levels.form.fee_value_hint')) ?></p>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    <?= e(t('common.save')) ?>
                </button>
                <?php if ($editingLevel !== null): ?>
                    <a href="/einstellungen/mahnwesen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
