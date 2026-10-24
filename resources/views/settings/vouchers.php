<?php
/** @var \App\Domain\Settings\VoucherSettings $settings */
/** @var array<string, string> $errors */

use function App\Infrastructure\Http\e;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Gutschein-Einstellungen</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Standard-Gültigkeitsdauer neu angelegter Gutscheine, falls beim Anlegen kein "Gültig bis"-Datum angegeben wird.
    </p>
</div>

<form method="post" action="/einstellungen/gutscheine" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <label for="default_validity_days" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Standard-Gültigkeitsdauer (Tage)</label>
        <input type="number" id="default_validity_days" name="default_validity_days" min="1" max="3650"
               value="<?= e((string) $settings->defaultValidityDays) ?>"
               class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['defaultValidityDays']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['defaultValidityDays'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['defaultValidityDays']) ?></p><?php endif; ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Speichern
        </button>
    </div>
</form>
