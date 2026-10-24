<?php
/** @var array<string, string> $errors */
/** @var bool $forced */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('profile.password.title')) ?></h1>
</div>

<?php if ($forced): ?>
    <div class="mb-6 max-w-lg rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
        <?= e(t('profile.password.forced_hint')) ?>
    </div>
<?php endif; ?>

<form method="post" action="/profil/passwort" class="max-w-lg space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label for="current_password" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('profile.password.field.current')) ?></label>
        <input type="password" id="current_password" name="current_password" required autofocus
               class="mt-1 w-full rounded-md border <?= isset($errors['currentPassword']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['currentPassword'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['currentPassword']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="new_password" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('profile.password.field.new')) ?></label>
        <input type="password" id="new_password" name="new_password" required
               class="mt-1 w-full rounded-md border <?= isset($errors['newPassword']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['newPassword'])): ?>
            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['newPassword']) ?></p>
        <?php else: ?>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('profile.password.field.new_hint')) ?></p>
        <?php endif; ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <?php if (!$forced): ?>
            <a href="/" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
        <?php endif; ?>
    </div>
</form>
