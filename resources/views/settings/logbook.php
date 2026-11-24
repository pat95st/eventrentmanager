<?php
/**
 * Global settings of the driver's logbook (migration 184).
 *
 * @var \App\Domain\Logbook\LogbookSettings $settings
 * @var array<string, string> $errors
 * @var array<string, string|null> $old
 */

use function App\Infrastructure\Http\e;

$value = array_key_exists('edit_window_days', $old) ? (string) $old['edit_window_days'] : (string) $settings->editWindowDays;
$border = isset($errors['editWindowDays']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700';
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Fahrtenbuch</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Regeln für die Erfassung von Fahrten. Die Einstellungen je Fahrzeug (QR-Code, externe Fahrer, Kilometerstand-Prüfung) stehen unter <a href="/fahrzeuge" class="underline">Fahrzeuge</a> › Fahrtenbuch.</p>
</div>

<form method="post" action="/einstellungen/fahrtenbuch" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label for="edit_window_days" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fahrer dürfen eigene Fahrten ändern und löschen (Tage nach dem Erfassen)</label>
        <input type="text" id="edit_window_days" name="edit_window_days" inputmode="numeric" required value="<?= e($value) ?>"
               class="mt-1 w-full max-w-[10rem] rounded-md border px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 <?= $border ?>">
        <?php if (isset($errors['editWindowDays'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['editWindowDays']) ?></p><?php endif; ?>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">0 = Fahrer können ihre Fahrten nach dem Speichern nicht mehr ändern. Fahrtenbuch-Verwalter können Fahrten immer ändern; jede Änderung wird protokolliert.</p>
    </div>
    <div class="form-actions flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
    </div>
</form>
