<?php
/** @var \App\Domain\Dashboard\DashboardWidget[] $allWidgets */
/** @var string[] $enabledKeys */
/** @var string[] $permittedKeys Schlüssel, für die der Benutzer überhaupt das nötige Recht besitzt. */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('profile.dashboard.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.dashboard.subtitle')) ?></p>
</div>

<div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <form method="post" action="/profil/dashboard">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

        <div class="space-y-3">
            <?php foreach ($allWidgets as $widget): ?>
                <?php $permitted = in_array($widget->value, $permittedKeys, true); ?>
                <label class="flex items-start gap-3 rounded-md border border-slate-200 p-3 <?= $permitted ? 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800' : 'opacity-50' ?> dark:border-slate-800">
                    <input
                        type="checkbox"
                        name="widgets[]"
                        value="<?= e($widget->value) ?>"
                        <?= in_array($widget->value, $enabledKeys, true) ? 'checked' : '' ?>
                        <?= $permitted ? '' : 'disabled' ?>
                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700"
                    >
                    <span>
                        <span class="block text-sm font-medium text-slate-900 dark:text-slate-100"><?= e($widget->label()) ?></span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($widget->description()) ?></span>
                        <?php if (!$permitted): ?>
                            <span class="mt-1 block text-xs text-amber-600 dark:text-amber-400"><?= e(t('profile.dashboard.missing_permission')) ?></span>
                        <?php endif; ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="mt-6">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('profile.dashboard.save')) ?>
            </button>
        </div>
    </form>
</div>
