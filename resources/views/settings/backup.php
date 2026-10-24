<?php
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, string $fallback = '') => array_key_exists($key, $old) ? (string) $old[$key] : $fallback;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.backup.title')) ?></h1>
</div>

<div class="max-w-2xl space-y-6">
    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.backup.export.title')) ?></h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.backup.export.description')) ?></p>

        <form method="post" action="/einstellungen/sicherung/export">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('settings.backup.export.button')) ?>
            </button>
        </form>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.backup.import.title')) ?></h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.backup.import.description')) ?></p>

        <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400">
            <?= e(t('settings.backup.import.warning')) ?>
        </div>

        <form method="post" action="/einstellungen/sicherung/import" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

            <div>
                <label for="backup_zip" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.backup.import.field.file')) ?></label>
                <input type="file" id="backup_zip" name="backup_zip" accept=".zip,application/zip" required
                       class="mt-1 block w-full text-sm text-slate-700 dark:text-slate-300">
                <?php if (isset($errors['backup_zip'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['backup_zip']) ?></p><?php endif; ?>
            </div>

            <div>
                <label for="confirmation_text" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.backup.import.field.confirmation')) ?></label>
                <input type="text" id="confirmation_text" name="confirmation_text" autocomplete="off"
                       value="<?= e($val('confirmation_text')) ?>"
                       class="mt-1 w-full max-w-xs rounded-md border <?= isset($errors['confirmation_text']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <?php if (isset($errors['confirmation_text'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['confirmation_text']) ?></p><?php endif; ?>
            </div>

            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                <?= e(t('settings.backup.import.button')) ?>
            </button>
        </form>
    </div>
</div>
