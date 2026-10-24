<?php
/** @var \App\Domain\User\User $user */
/** @var array<string, string> $errors */

use function App\Infrastructure\Http\e;
?>

<div class="mb-6">
    <a href="/benutzer" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Passwort zurücksetzen</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($user->email) ?></p>
</div>

<form method="post" action="/benutzer/<?= e($user->id) ?>/passwort" class="max-w-lg space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label for="new_password" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Neues Passwort *</label>
        <input type="password" id="new_password" name="new_password" required autofocus
               class="mt-1 w-full rounded-md border <?= isset($errors['password']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['password'])): ?>
            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['password']) ?></p>
        <?php else: ?>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Mindestens 10 Zeichen. Der Benutzer muss es beim nächsten Login ändern.</p>
        <?php endif; ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Zurücksetzen
        </button>
        <a href="/benutzer" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
    </div>
</form>
