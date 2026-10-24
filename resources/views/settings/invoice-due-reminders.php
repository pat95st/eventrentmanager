<?php
/** @var \App\Domain\Settings\InvoiceDueReminderSettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, ?string $fallback) => array_key_exists($key, $old) ? (string) $old[$key] : ($fallback ?? '');
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.invoice_due_reminders.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.invoice_due_reminders.subtitle')) ?>
    </p>
</div>

<form method="post" action="/einstellungen/rechnungserinnerungen" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="lead_days" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.invoice_due_reminders.field.lead_days')) ?></label>
            <input type="number" id="lead_days" name="lead_days" min="1" max="90" inputmode="numeric"
                   value="<?= e($val('lead_days', (string) $settings->leadDays)) ?>"
                   class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['leadDays']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.invoice_due_reminders.field.lead_days_hint')) ?></p>
            <?php if (isset($errors['leadDays'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['leadDays']) ?></p><?php endif; ?>
        </div>
        <div class="flex items-end pb-2">
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="enabled" value="1" <?= (array_key_exists('enabled', $old) ? $old['enabled'] === '1' : $settings->enabled) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <?= e(t('settings.invoice_due_reminders.field.enabled')) ?>
            </label>
        </div>
    </div>

    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.invoice_due_reminders.hint')) ?></p>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>
