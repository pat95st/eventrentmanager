<?php
/** @var \App\Domain\Settings\SecuritySettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var array<string, array<string, array{label: string, requiredByDefault: bool}>> $confirmGroups */

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

<form method="post" action="/einstellungen/sicherheit" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
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

<form method="post" action="/einstellungen/sicherheit/rueckfragen" id="rueckfragen" class="mt-6 space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Rückfragen überspringen</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Wer beim Klick auf „Löschen“ und ähnliche Schaltflächen die Umschalttaste (Shift) gedrückt hält, führt die Aktion ohne Rückfrage aus.
            Das gilt auch für die Live-Ansichten (FOH-Minutenprogramm, Displays, Veranstaltungs-Audio).
        </p>
    </div>

    <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
        <input type="checkbox" name="confirm_bypass_enabled" value="1" <?= $settings->confirmBypassEnabled ? 'checked' : '' ?>
               class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
        <span>
            Überspringen mit gedrückter Umschalttaste erlauben
            <span class="block text-xs text-slate-400 dark:text-slate-500">Jeder Benutzer kann es für sich unter Profil › Darstellung abschalten.</span>
        </span>
    </label>

    <div>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Rückfragen, die sich nie überspringen lassen</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Angehakte Rückfragen erscheinen immer, auch mit gedrückter Umschalttaste. Alle anderen lassen sich überspringen.
        </p>
        <div class="mt-3 space-y-2">
            <?php foreach ($confirmGroups as $group => $entries): ?>
                <?php $requiredCount = count(array_filter(array_keys($entries), static fn (string $key): bool => $settings->isConfirmRequired($key))); ?>
                <details class="rounded-md border border-slate-200 dark:border-slate-800" <?= $requiredCount > 0 ? 'open' : '' ?>>
                    <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <?= e($group) ?>
                        <span class="ml-1 text-xs font-normal text-slate-400 dark:text-slate-500"><?= $requiredCount ?> von <?= count($entries) ?> Pflicht</span>
                    </summary>
                    <div class="grid gap-x-6 gap-y-1.5 border-t border-slate-100 px-3 py-3 sm:grid-cols-2 dark:border-slate-800">
                        <?php foreach ($entries as $key => $entry): ?>
                            <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <input type="checkbox" name="required[]" value="<?= e($key) ?>" <?= $settings->isConfirmRequired($key) ? 'checked' : '' ?>
                                       class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                                <span>
                                    <?= e($entry['label']) ?>
                                    <?php if ($entry['requiredByDefault']): ?>
                                        <span class="text-xs text-slate-400 dark:text-slate-500">(Standard: Pflicht)</span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
        <button type="submit" form="rueckfragen-reset" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Auf Standard zurücksetzen</button>
    </div>
</form>
<form method="post" action="/einstellungen/sicherheit/rueckfragen" id="rueckfragen-reset" hidden
      data-confirm-key="settings.security.confirm.reset" data-confirm="Die Liste der Pflicht-Rückfragen auf den Standard zurücksetzen? Der Schalter oben bleibt unverändert.">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <input type="hidden" name="reset" value="1">
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
