<?php
/** @var \App\Domain\Push\PushSettings $settings */
/** @var string $subjectEmailInput */
/** @var array<string, string> $errors */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.push.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.push.subtitle')) ?></p>
</div>

<div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.push.keys.title')) ?></h2>
    <p class="mb-3 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.push.keys.hint')) ?></p>

    <?php if ($settings->hasKeys()): ?>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.push.keys.public_key_label')) ?></label>
        <p class="mt-1 break-all rounded-md bg-slate-100 px-3 py-2 font-mono text-xs text-slate-800 dark:bg-slate-800 dark:text-slate-200"><?= e($settings->vapidPublicKey) ?></p>
    <?php else: ?>
        <p class="text-sm text-red-600 dark:text-red-400"><?= e(t('settings.push.keys.missing')) ?></p>
    <?php endif; ?>
</div>

<form method="post" action="/einstellungen/push" class="mt-6 max-w-2xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <label for="subject_email" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.push.field.subject_email')) ?></label>
        <p class="mb-2 mt-0.5 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.push.field.subject_email_hint')) ?></p>
        <input type="email" id="subject_email" name="subject_email" value="<?= e($subjectEmailInput) ?>" placeholder="admin@beispiel.de"
               class="mt-1 w-full max-w-sm rounded-md border <?= isset($errors['subjectEmail']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['subjectEmail'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['subjectEmail']) ?></p><?php endif; ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>

<div class="mt-6 max-w-2xl rounded-lg border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-950/30">
    <h2 class="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-300"><?= e(t('settings.push.info.title')) ?></h2>
    <p class="text-xs text-amber-700 dark:text-amber-400"><?= e(t('settings.push.info.text')) ?></p>
</div>
