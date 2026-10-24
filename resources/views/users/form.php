<?php
/** @var \App\Domain\User\User|null $user */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var \App\Domain\User\Permission[] $allPermissions */

use function App\Infrastructure\Http\e;

$val = fn (string $field, mixed $fallback = null) => array_key_exists($field, $old) ? $old[$field] : $fallback;
$selectedPermissions = $val('permissions', array_map(static fn ($p) => $p->value, $user->permissions ?? []));
$isActive = $val('is_active', $user->isActive ?? true);
$mustSetupTwoFactor = $val('must_setup_two_factor', $user->mustSetupTwoFactor ?? false);
?>

<div class="mb-6">
    <a href="/benutzer" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $user !== null ? 'Benutzer bearbeiten' : 'Neuer Benutzer' ?></h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/benutzer/' . e($editingId) : '/benutzer' ?>"
    class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label for="email" class="block text-xs font-medium text-slate-600 dark:text-slate-400">E-Mail-Adresse *</label>
        <input type="email" id="email" name="email" required
               value="<?= e($val('email', $user->email ?? '')) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['email']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['email'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="first_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Vorname <?= $user === null ? '*' : '' ?></label>
            <input type="text" id="first_name" name="first_name" <?= $user === null ? 'required' : '' ?>
                   value="<?= e($val('first_name', $user->firstName ?? '')) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['first_name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['first_name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['first_name']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="last_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Nachname <?= $user === null ? '*' : '' ?></label>
            <input type="text" id="last_name" name="last_name" <?= $user === null ? 'required' : '' ?>
                   value="<?= e($val('last_name', $user->lastName ?? '')) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['last_name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['last_name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['last_name']) ?></p><?php endif; ?>
        </div>
    </div>

    <?php if ($user === null): ?>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Zugang</label>
            <div class="mt-2 space-y-2">
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="radio" name="account_mode" value="password" id="account_mode_password" checked>
                    Passwort jetzt festlegen
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="radio" name="account_mode" value="invite" id="account_mode_invite">
                    Per E-Mail einladen (Link zum Festlegen senden)
                </label>
            </div>
        </div>

        <div id="password_field_wrapper">
            <label for="password" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Erstpasswort *</label>
            <input type="password" id="password" name="password" required
                   class="mt-1 w-full rounded-md border <?= isset($errors['password']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['password'])): ?>
                <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['password']) ?></p>
            <?php else: ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Mindestens 10 Zeichen. Muss beim ersten Login geändert werden.</p>
            <?php endif; ?>
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="must_setup_two_factor" value="1" <?= $mustSetupTwoFactor ? 'checked' : '' ?>>
                Zwei-Faktor-Authentifizierung bei erster Anmeldung erzwingen
            </label>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Der Benutzer wird nach der Anmeldung zur Einrichtung eines zweiten Faktors verpflichtet, bevor er die Anwendung sonst nutzen kann.</p>
        </div>

        <script>
            (function () {
                var passwordMode = document.getElementById('account_mode_password');
                var inviteMode = document.getElementById('account_mode_invite');
                var wrapper = document.getElementById('password_field_wrapper');
                var passwordInput = document.getElementById('password');

                function sync() {
                    var invite = inviteMode.checked;
                    wrapper.classList.toggle('hidden', invite);
                    passwordInput.required = !invite;
                }

                passwordMode.addEventListener('change', sync);
                inviteMode.addEventListener('change', sync);
                sync();
            })();
        </script>
    <?php endif; ?>

    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rechte *</label>
        <div class="mt-2 space-y-2">
            <?php foreach ($allPermissions as $permission): ?>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="permissions[]" value="<?= e($permission->value) ?>"
                           <?= in_array($permission->value, $selectedPermissions, true) ? 'checked' : '' ?>>
                    <?= e($permission->label()) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if (isset($errors['permissions'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['permissions']) ?></p><?php endif; ?>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">"Administration" schließt automatisch alle übrigen Rechte ein.</p>
    </div>

    <?php if ($user !== null): ?>
        <div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                Konto aktiv
            </label>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Ein deaktiviertes Konto kann sich nicht mehr anmelden.</p>
        </div>
    <?php endif; ?>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Speichern
        </button>
        <a href="/benutzer" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
    </div>
</form>
