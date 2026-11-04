<?php
/** @var \App\Domain\Settings\SecuritySettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use App\Domain\Settings\SecuritySettings;

use function App\Infrastructure\Http\e;

$value = array_key_exists('session_timeout_minutes', $old) ? (string) $old['session_timeout_minutes'] : (string) $settings->sessionTimeoutMinutes;
$presets = [15, 30, 60, 120, 240, 480, 1440, 10080];
$input = 'mt-1 w-full rounded-md border px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 '
    . (isset($errors['session_timeout_minutes']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700');
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Sicherheit</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Einstellungen, die für alle Benutzer gelten. Nur Administratoren können sie ändern.</p>
</div>

<form method="post" action="/einstellungen/sicherheit" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Automatische Abmeldung</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Wer so lange nichts in der Anwendung macht, wird automatisch abgemeldet und muss sich neu anmelden.
            Kurz vorher erscheint ein Hinweis mit „Angemeldet bleiben“. Gilt für alle Benutzer und Geräte, auch für die installierte App.
        </p>
    </div>

    <div>
        <label for="session_timeout_minutes" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Abmelden nach Inaktivität (Minuten)</label>
        <div class="flex flex-wrap items-center gap-3">
            <input type="number" id="session_timeout_minutes" name="session_timeout_minutes" required
                   min="<?= SecuritySettings::MIN_SESSION_TIMEOUT_MINUTES ?>" max="<?= SecuritySettings::MAX_SESSION_TIMEOUT_MINUTES ?>" step="1"
                   value="<?= e($value) ?>" class="<?= $input ?>" style="max-width:9rem" aria-describedby="session_timeout_hint">
            <span id="session_timeout_human" class="mt-1 text-sm text-slate-600 dark:text-slate-400"><?= ctype_digit($value) ? '= ' . e(SecuritySettings::formatMinutes((int) $value)) : '' ?></span>
        </div>
        <?php if (isset($errors['session_timeout_minutes'])): ?>
            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['session_timeout_minutes']) ?></p>
        <?php endif; ?>
        <p id="session_timeout_hint" class="mt-1 text-xs text-slate-400 dark:text-slate-500">
            <?= SecuritySettings::MIN_SESSION_TIMEOUT_MINUTES ?> Minuten bis 30 Tage. Standard: <?= e(SecuritySettings::formatMinutes(SecuritySettings::DEFAULT_SESSION_TIMEOUT_MINUTES)) ?>.
        </p>
        <div class="mt-2 flex flex-wrap gap-2" role="group" aria-label="Schnellauswahl">
            <?php foreach ($presets as $preset): ?>
                <button type="button" data-timeout-preset="<?= $preset ?>" class="rounded-full border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(SecuritySettings::formatMinutes($preset)) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <p class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
        Eine Änderung gilt ab sofort, auch für bereits angemeldete Benutzer: Wer schon länger als die neue Zeit inaktiv ist, wird beim nächsten Aufruf abgemeldet.
    </p>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
    </div>
</form>

<script>
    (function () {
        var input = document.getElementById('session_timeout_minutes');
        var human = document.getElementById('session_timeout_human');
        function format(m) {
            if (m % 1440 === 0) { var d = m / 1440; return d + (d === 1 ? ' Tag' : ' Tage'); }
            if (m % 60 === 0) { var h = m / 60; return h + (h === 1 ? ' Stunde' : ' Stunden'); }
            return m + (m === 1 ? ' Minute' : ' Minuten');
        }
        function update() {
            var m = parseInt(input.value, 10);
            human.textContent = m > 0 ? '= ' + format(m) : '';
        }
        input.addEventListener('input', update);
        document.querySelectorAll('[data-timeout-preset]').forEach(function (button) {
            button.addEventListener('click', function () {
                input.value = button.getAttribute('data-timeout-preset');
                update();
                input.focus();
            });
        });
    })();
</script>
