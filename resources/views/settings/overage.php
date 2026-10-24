<?php
/** @var \App\Domain\Settings\OverageSettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, ?string $fallback) => array_key_exists($key, $old) ? (string) $old[$key] : ($fallback ?? '');
$checkedFlatFeeEnabled = array_key_exists('flat_fee_enabled', $old) ? $old['flat_fee_enabled'] === '1' : $settings->flatFeeEnabled;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.overage.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.overage.subtitle')) ?>
    </p>
</div>

<div class="mb-6 max-w-2xl rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
    <?= e(t('settings.overage.fixed_rule_hint')) ?>
</div>

<form method="post" action="/einstellungen/ueberziehung" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="flex items-start gap-2">
        <input type="checkbox" id="flat_fee_enabled" name="flat_fee_enabled" value="1" <?= $checkedFlatFeeEnabled ? 'checked' : '' ?>
               class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
        <div>
            <label for="flat_fee_enabled" class="block text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('settings.overage.field.flat_fee_enabled')) ?></label>
            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.overage.field.flat_fee_enabled_hint')) ?></p>
        </div>
    </div>

    <div>
        <label for="flat_fee_amount" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.overage.field.flat_fee_amount')) ?></label>
        <div class="mt-1 flex max-w-[10rem] items-center gap-1">
            <input type="text" id="flat_fee_amount" name="flat_fee_amount" inputmode="decimal"
                   value="<?= e($val('flat_fee_amount', $settings->flatFeeAmount)) ?>"
                   class="w-full rounded-md border <?= isset($errors['flatFeeAmount']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <span class="text-sm text-slate-500 dark:text-slate-400">€</span>
        </div>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.overage.field.flat_fee_amount_hint')) ?></p>
        <?php if (isset($errors['flatFeeAmount'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['flatFeeAmount']) ?></p><?php endif; ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>
