<?php
/** @var \App\Domain\Settings\RentalReminderSettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, ?string $fallback) => array_key_exists($key, $old) ? (string) $old[$key] : ($fallback ?? '');
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.rental_reminders.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.rental_reminders.subtitle')) ?>
    </p>
</div>

<form method="post" action="/einstellungen/erinnerungen" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="pickup_lead_hours" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.rental_reminders.field.pickup_lead_hours')) ?></label>
            <input type="number" id="pickup_lead_hours" name="pickup_lead_hours" min="1" max="720" inputmode="numeric"
                   value="<?= e($val('pickup_lead_hours', (string) $settings->pickupLeadHours)) ?>"
                   class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['pickupLeadHours']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.rental_reminders.field.pickup_lead_hours_hint')) ?></p>
            <?php if (isset($errors['pickupLeadHours'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['pickupLeadHours']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="return_lead_hours" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.rental_reminders.field.return_lead_hours')) ?></label>
            <input type="number" id="return_lead_hours" name="return_lead_hours" min="1" max="720" inputmode="numeric"
                   value="<?= e($val('return_lead_hours', (string) $settings->returnLeadHours)) ?>"
                   class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['returnLeadHours']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.rental_reminders.field.return_lead_hours_hint')) ?></p>
            <?php if (isset($errors['returnLeadHours'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['returnLeadHours']) ?></p><?php endif; ?>
        </div>
    </div>

    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.rental_reminders.hint')) ?></p>

    <div class="border-t border-slate-200 pt-5 dark:border-slate-800">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.rental_reminders.checklist.title')) ?></h2>
        <p class="mb-3 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.rental_reminders.checklist.subtitle')) ?></p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="checklist_lead_hours" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.rental_reminders.field.checklist_lead_hours')) ?></label>
                <input type="number" id="checklist_lead_hours" name="checklist_lead_hours" min="1" max="720" inputmode="numeric"
                       value="<?= e($val('checklist_lead_hours', (string) $settings->checklistLeadHours)) ?>"
                       class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['checklistLeadHours']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.rental_reminders.field.checklist_lead_hours_hint')) ?></p>
                <?php if (isset($errors['checklistLeadHours'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['checklistLeadHours']) ?></p><?php endif; ?>
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="checklist_auto_send_enabled" value="1" <?= (array_key_exists('checklist_auto_send_enabled', $old) ? $old['checklist_auto_send_enabled'] === '1' : $settings->checklistAutoSendEnabled) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <?= e(t('settings.rental_reminders.field.checklist_auto_send_enabled')) ?>
                </label>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>
