<?php
/** @var \App\Domain\Settings\InvoiceSettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, ?string $fallback) => array_key_exists($key, $old) ? (string) $old[$key] : ($fallback ?? '');
$checkedSmallBusiness = array_key_exists('is_small_business', $old) ? $old['is_small_business'] === '1' : $settings->isSmallBusiness;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.invoice_settings.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.invoice_settings.subtitle')) ?>
    </p>
</div>

<form method="post" action="/einstellungen/rechnung" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="flex items-start gap-2">
        <input type="checkbox" id="is_small_business" name="is_small_business" value="1" <?= $checkedSmallBusiness ? 'checked' : '' ?>
               class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
        <div>
            <label for="is_small_business" class="block text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('settings.invoice_settings.field.is_small_business')) ?></label>
            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.invoice_settings.field.is_small_business_hint')) ?></p>
        </div>
    </div>

    <div>
        <label for="vat_rate_percent" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.invoice_settings.field.vat_rate_percent')) ?></label>
        <input type="text" id="vat_rate_percent" name="vat_rate_percent" inputmode="decimal"
               value="<?= e($val('vat_rate_percent', $settings->vatRatePercent)) ?>"
               class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['vatRatePercent']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['vatRatePercent'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['vatRatePercent']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="tax_number" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.invoice_settings.field.tax_number')) ?></label>
            <input type="text" id="tax_number" name="tax_number"
                   value="<?= e($val('tax_number', $settings->taxNumber)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div>
            <label for="vat_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.invoice_settings.field.vat_id')) ?></label>
            <input type="text" id="vat_id" name="vat_id"
                   value="<?= e($val('vat_id', $settings->vatId)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="payment_term_days" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.invoice_settings.field.payment_term_days')) ?></label>
            <input type="number" id="payment_term_days" name="payment_term_days" min="1" step="1"
                   value="<?= e($val('payment_term_days', (string) $settings->paymentTermDays)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['paymentTermDays']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['paymentTermDays'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['paymentTermDays']) ?></p><?php endif; ?>
        </div>
    </div>

    <p class="text-xs text-slate-400 dark:text-slate-500"><?= t('settings.invoice_settings.numbering_hint', ['link' => '<a href="/einstellungen/nummerierung" class="underline">' . e(t('nav.numbering_settings')) . '</a>']) ?></p>

    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.invoice_settings.bank.title')) ?></h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label for="bank_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.invoice_settings.field.bank_name')) ?></label>
                <input type="text" id="bank_name" name="bank_name"
                       value="<?= e($val('bank_name', $settings->bankName)) ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <div>
                <label for="iban" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.invoice_settings.field.iban')) ?></label>
                <input type="text" id="iban" name="iban"
                       value="<?= e($val('iban', $settings->iban)) ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <div>
                <label for="bic" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.invoice_settings.field.bic')) ?></label>
                <input type="text" id="bic" name="bic"
                       value="<?= e($val('bic', $settings->bic)) ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>
