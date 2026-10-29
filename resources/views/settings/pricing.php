<?php
/** @var \App\Domain\Settings\PricingSettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, ?string $fallback) => array_key_exists($key, $old) ? (string) $old[$key] : ($fallback ?? '');
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.pricing.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.pricing.subtitle')) ?>
    </p>
</div>

<form method="post" action="/einstellungen/preise" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <label for="accessory_flat_rate_percent" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.pricing.field.accessory_flat_rate_percent')) ?></label>
        <p class="mb-2 mt-0.5 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.pricing.field.accessory_flat_rate_percent_hint')) ?></p>
        <input type="text" id="accessory_flat_rate_percent" name="accessory_flat_rate_percent" inputmode="decimal"
               value="<?= e($val('accessory_flat_rate_percent', $settings->accessoryFlatRatePercent)) ?>"
               class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['accessoryFlatRatePercent']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['accessoryFlatRatePercent'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['accessoryFlatRatePercent']) ?></p><?php endif; ?>
    </div>

    <p class="border-t border-slate-100 pt-4 text-xs text-slate-400 dark:border-slate-800 dark:text-slate-500">
        <?= e(t('settings.pricing.rounding.moved_hint')) ?>
        <a href="/einstellungen/preisstaffelung" class="text-brand-700 hover:underline dark:text-brand-400"><?= e(t('settings.price_tiers.title')) ?></a>
    </p>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>
