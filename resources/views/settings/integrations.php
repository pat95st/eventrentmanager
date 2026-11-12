<?php
/**
 * @var \App\Domain\Settings\IntegrationBaseSettings $baseSettings
 * @var \App\Domain\Settings\GoogleMerchantSettings $googleMerchantSettings
 * @var \App\Domain\Settings\GoogleBusinessSettings $googleBusinessSettings
 * @var \App\Domain\Settings\WhatsappBusinessSettings $whatsappSettings
 * @var \App\Domain\Datev\DatevSettings $datevSettings
 * @var \App\Domain\Datev\DatevExport[] $datevExports
 * @var array<string, string|null> $datevOld
 * @var array<string, string> $errors
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.integrations.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.integrations.intro')) ?></p>
</div>

<div class="space-y-6">
    <!-- Basis-URL -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.integrations.base_url.title')) ?></h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.integrations.base_url.hint')) ?></p>
        <form method="post" action="/einstellungen/integrationen/basis-url" class="flex flex-wrap items-end gap-3">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div class="flex-1 min-w-[260px]">
                <input type="text" name="base_url" value="<?= e($baseSettings->baseUrl ?? '') ?>" placeholder="https://verleih.example.de"
                       class="mt-1 w-full rounded-md border <?= isset($errors['baseUrl']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <?php if (isset($errors['baseUrl'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['baseUrl']) ?></p><?php endif; ?>
            </div>
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
        </form>
    </div>

    <!-- Google Merchant Center -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.integrations.google_merchant.title')) ?></h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.integrations.google_merchant.hint')) ?></p>

        <form method="post" action="/einstellungen/integrationen/google-merchant" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.google_merchant.merchant_id')) ?></label>
                <input type="text" name="merchant_id" value="<?= e($googleMerchantSettings->merchantId ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.google_merchant.target_country')) ?></label>
                <input type="text" name="target_country" value="<?= e($googleMerchantSettings->targetCountry) ?>" maxlength="2"
                       class="mt-1 w-full rounded-md border <?= isset($errors['targetCountry']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm font-mono uppercase focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.google_merchant.content_language')) ?></label>
                <input type="text" name="content_language" value="<?= e($googleMerchantSettings->contentLanguage) ?>" maxlength="5"
                       class="mt-1 w-full rounded-md border <?= isset($errors['contentLanguage']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.google_merchant.product_link_template')) ?></label>
                <input type="text" name="product_link_template" value="<?= e($googleMerchantSettings->productLinkTemplate ?? '') ?>" placeholder="https://meine-website.de/produkte/{id}"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.google_merchant.product_link_template_hint')) ?></p>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
            </div>
        </form>

        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.google_merchant.service_account')) ?></p>
            <?php if ($googleMerchantSettings->serviceAccountJsonFilename !== null): ?>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-700">
                        <?= e(t('settings.integrations.google_merchant.service_account_present')) ?>
                    </span>
                    <form method="post" action="/einstellungen/integrationen/google-merchant/zugangsdaten/loeschen" data-confirm="<?= e(t('settings.integrations.google_merchant.confirm_remove_service_account')) ?>" data-confirm-style="danger">
                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                    </form>
                </div>
            <?php else: ?>
                <p class="mb-2 text-sm text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.google_merchant.service_account_missing')) ?></p>
            <?php endif; ?>
            <form method="post" action="/einstellungen/integrationen/google-merchant/zugangsdaten" enctype="multipart/form-data" class="mt-2">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <label class="inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">
                    <?= e(t('settings.integrations.google_merchant.upload_service_account')) ?>
                    <input type="file" name="service_account_json" accept="application/json,.json" class="hidden" onchange="this.form.submit()">
                </label>
            </form>
        </div>
    </div>

    <!-- Google Unternehmensprofil -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.integrations.google_business.title')) ?></h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.integrations.google_business.hint')) ?></p>

        <form method="post" action="/einstellungen/integrationen/google-business" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.google_business.client_id')) ?></label>
                <input type="text" name="client_id" value="<?= e($googleBusinessSettings->clientId ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.google_business.client_secret')) ?></label>
                <input type="password" name="client_secret" value="<?= e($googleBusinessSettings->clientSecret ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <?php if (isset($errors['client'])): ?><p class="sm:col-span-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['client']) ?></p><?php endif; ?>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
            </div>
        </form>

        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
            <?php if ($googleBusinessSettings->connectedAt !== null): ?>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-700">
                        <?= e(t('settings.integrations.google_business.connected', ['date' => $googleBusinessSettings->connectedAt->format('d.m.Y H:i')])) ?>
                    </span>
                    <?php if ($googleBusinessSettings->locationId === null): ?>
                        <span class="text-xs text-amber-600 dark:text-amber-400"><?= e(t('settings.integrations.google_business.no_location')) ?></span>
                    <?php endif; ?>
                    <form method="post" action="/einstellungen/integrationen/google-business/trennen" data-confirm="<?= e(t('settings.integrations.google_business.confirm_disconnect')) ?>" data-confirm-style="danger">
                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('settings.integrations.google_business.disconnect')) ?></button>
                    </form>
                </div>
            <?php else: ?>
                <a href="/einstellungen/integrationen/google-business/verbinden" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <?= e(t('settings.integrations.google_business.connect')) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- WhatsApp Business -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.integrations.whatsapp.title')) ?></h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.integrations.whatsapp.hint')) ?></p>

        <form method="post" action="/einstellungen/integrationen/whatsapp" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.whatsapp.access_token')) ?></label>
                <input type="password" name="access_token" value="<?= e($whatsappSettings->accessToken ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.whatsapp.catalog_id')) ?></label>
                <input type="text" name="catalog_id" value="<?= e($whatsappSettings->catalogId ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.whatsapp.currency')) ?></label>
                <input type="text" name="currency" value="<?= e($whatsappSettings->currency) ?>" maxlength="3"
                       class="mt-1 w-full rounded-md border <?= isset($errors['currency']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm font-mono uppercase focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
            </div>
        </form>
    </div>
    <!-- DATEV -->
    <?php
    $datevSubmitted = isset($datevOld['_submitted']);
    $dv = static fn (string $key, $current): string => $datevSubmitted ? (string) ($datevOld[$key] ?? '') : (string) ($current ?? '');
    $dc = static fn (string $key, bool $current): bool => $datevSubmitted ? ($datevOld[$key] ?? null) !== null : $current;
    $inputClass = static fn (string $errorKey): string => 'mt-1 w-full rounded-md border ' . (isset($errors[$errorKey]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700') . ' px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900';
    $selectClass = static fn (string $errorKey): string => str_replace(' font-mono', '', $inputClass($errorKey));
    $fieldError = static fn (string $errorKey): string => isset($errors[$errorKey]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$errorKey]) . '</p>' : '';
    [$datevDefaultFrom, $datevDefaultTo] = $datevSettings->interval->previousPeriod(new DateTimeImmutable());
    $datevReady = $datevSettings->enabled && $datevSettings->missingRequirements() === [];
    ?>
    <div id="datev" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-1 flex flex-wrap items-center gap-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.integrations.datev.title')) ?></h2>
            <?php if ($datevSettings->enabled): ?>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-700"><?= e(t('settings.integrations.datev.active')) ?></span>
            <?php endif; ?>
            <?php if ($datevSettings->autoSendEnabled): ?>
                <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-300 dark:bg-blue-950/40 dark:text-blue-400 dark:ring-blue-700"><?= e(t('settings.integrations.datev.auto_badge', ['interval' => $datevSettings->interval->label()])) ?></span>
            <?php endif; ?>
        </div>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.integrations.datev.hint')) ?></p>
        <?php if (isset($errors['datev'])): ?><p class="mb-3 text-xs text-red-600 dark:text-red-400"><?= e($errors['datev']) ?></p><?php endif; ?>

        <form method="post" action="/einstellungen/integrationen/datev" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="datev_enabled" value="1" <?= $dc('datev_enabled', $datevSettings->enabled) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('settings.integrations.datev.enabled')) ?>
            </label>

            <fieldset>
                <legend class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.datev.section_client')) ?></legend>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.consultant_number')) ?></label>
                        <input type="text" inputmode="numeric" name="datev_consultant_number" value="<?= e($dv('datev_consultant_number', $datevSettings->consultantNumber)) ?>" placeholder="z. B. 29098" class="<?= $inputClass('datev_consultant_number') ?>">
                        <?= $fieldError('datev_consultant_number') ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.client_number')) ?></label>
                        <input type="text" inputmode="numeric" name="datev_client_number" value="<?= e($dv('datev_client_number', $datevSettings->clientNumber)) ?>" placeholder="z. B. 55003" class="<?= $inputClass('datev_client_number') ?>">
                        <?= $fieldError('datev_client_number') ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.chart_of_accounts')) ?></label>
                        <?php $chartValue = $dv('datev_chart_of_accounts', $datevSettings->chartOfAccounts->value); ?>
                        <select name="datev_chart_of_accounts" class="<?= $selectClass('datev_chart_of_accounts') ?>">
                            <?php foreach (\App\Domain\Datev\DatevChartOfAccounts::cases() as $chart): ?>
                                <option value="<?= e($chart->value) ?>" <?= $chartValue === $chart->value ? 'selected' : '' ?>><?= e($chart->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.account_length')) ?></label>
                        <?php $lengthValue = $dv('datev_account_length', $datevSettings->accountLength); ?>
                        <select name="datev_account_length" class="<?= $selectClass('datev_account_length') ?>">
                            <?php for ($length = 4; $length <= 8; $length++): ?>
                                <option value="<?= $length ?>" <?= $lengthValue === (string) $length ? 'selected' : '' ?>><?= $length ?></option>
                            <?php endfor; ?>
                        </select>
                        <?= $fieldError('datev_account_length') ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.fiscal_year_start')) ?></label>
                        <?php $fyValue = $dv('datev_fiscal_year_start_month', $datevSettings->fiscalYearStartMonth); ?>
                        <select name="datev_fiscal_year_start_month" class="<?= $selectClass('datev_fiscal_year_start_month') ?>">
                            <?php for ($month = 1; $month <= 12; $month++): ?>
                                <option value="<?= $month ?>" <?= $fyValue === (string) $month ? 'selected' : '' ?>><?= e(t('articles.show.month.' . $month)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.datev.client_hint')) ?></p>
            </fieldset>

            <fieldset>
                <legend class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.datev.section_accounts')) ?></legend>
                <p class="mb-3 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.datev.accounts_hint')) ?></p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.debtor_mode')) ?></label>
                        <?php $debtorModeValue = $dv('datev_debtor_mode', $datevSettings->debtorMode->value); ?>
                        <select name="datev_debtor_mode" class="<?= $selectClass('datev_debtor_mode') ?>">
                            <?php foreach (\App\Domain\Datev\DatevDebtorMode::cases() as $mode): ?>
                                <option value="<?= e($mode->value) ?>" <?= $debtorModeValue === $mode->value ? 'selected' : '' ?>><?= e($mode->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.debtor_account')) ?></label>
                        <input type="text" inputmode="numeric" name="datev_debtor_account" value="<?= e($dv('datev_debtor_account', $datevSettings->debtorAccount)) ?>" placeholder="<?= e((string) $datevSettings->defaultDebtorAccount()) ?>" class="<?= $inputClass('datev_debtor_account') ?>">
                        <?= $fieldError('datev_debtor_account') ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.creditor_account')) ?></label>
                        <input type="text" inputmode="numeric" name="datev_creditor_account" value="<?= e($dv('datev_creditor_account', $datevSettings->creditorAccount)) ?>" placeholder="<?= e((string) $datevSettings->defaultCreditorAccount()) ?>" class="<?= $inputClass('datev_creditor_account') ?>">
                        <?= $fieldError('datev_creditor_account') ?>
                    </div>
                    <?php foreach (\App\Domain\Datev\DatevAccountRole::cases() as $role): ?>
                        <?php $key = 'datev_account_' . $role->value; ?>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e($role->label()) ?></label>
                            <input type="text" inputmode="numeric" name="<?= e($key) ?>" value="<?= e($dv($key, $datevSettings->accounts[$role->value] ?? null)) ?>" placeholder="<?= e((string) $datevSettings->defaultAccount($role)) ?>" class="<?= $inputClass($key) ?>">
                            <?= $fieldError($key) ?>
                        </div>
                    <?php endforeach; ?>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.receipt_tax_key')) ?></label>
                        <input type="text" inputmode="numeric" name="datev_receipt_tax_key" value="<?= e($dv('datev_receipt_tax_key', $datevSettings->receiptTaxKey)) ?>" placeholder="<?= e(t('settings.integrations.datev.receipt_tax_key_placeholder')) ?>" class="<?= $inputClass('datev_receipt_tax_key') ?>">
                        <?= $fieldError('datev_receipt_tax_key') ?>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.datev.section_content')) ?></legend>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="datev_include_receipts" value="1" <?= $dc('datev_include_receipts', $datevSettings->includeReceipts) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                        <?= e(t('settings.integrations.datev.include_receipts')) ?>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="datev_include_documents" value="1" <?= $dc('datev_include_documents', $datevSettings->includeDocuments) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                        <?= e(t('settings.integrations.datev.include_documents')) ?>
                    </label>
                </div>
            </fieldset>

            <fieldset>
                <legend class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.datev.section_delivery')) ?></legend>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.advisor_email')) ?></label>
                        <input type="email" name="datev_advisor_email" value="<?= e($dv('datev_advisor_email', $datevSettings->advisorEmail)) ?>" placeholder="kanzlei@steuerberater.de" class="<?= $inputClass('datev_advisor_email') ?>">
                        <?= $fieldError('datev_advisor_email') ?>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="checkbox" name="datev_auto_send_enabled" value="1" <?= $dc('datev_auto_send_enabled', $datevSettings->autoSendEnabled) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                            <?= e(t('settings.integrations.datev.auto_send')) ?>
                        </label>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.interval')) ?></label>
                        <?php $intervalValue = $dv('datev_interval', $datevSettings->interval->value); ?>
                        <select name="datev_interval" class="<?= $selectClass('datev_interval') ?>">
                            <?php foreach (\App\Domain\Datev\DatevExportInterval::cases() as $interval): ?>
                                <option value="<?= e($interval->value) ?>" <?= $intervalValue === $interval->value ? 'selected' : '' ?>><?= e($interval->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.send_day')) ?></label>
                        <input type="number" min="1" max="28" name="datev_send_day" value="<?= e($dv('datev_send_day', $datevSettings->sendDay)) ?>" class="<?= $inputClass('datev_send_day') ?>">
                        <?= $fieldError('datev_send_day') ?>
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.datev.auto_send_hint')) ?></p>
            </fieldset>

            <div>
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
            </div>
        </form>

        <div class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800">
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.datev.export_title')) ?></p>
            <?php if (!$datevReady): ?>
                <p class="text-sm text-slate-400 dark:text-slate-500"><?= e(t('settings.integrations.datev.export_not_ready')) ?></p>
            <?php else: ?>
                <form method="post" action="/einstellungen/integrationen/datev/export" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.from')) ?></label>
                        <input type="date" name="datev_from" value="<?= e($datevDefaultFrom->format('Y-m-d')) ?>" required class="<?= $inputClass('datev_from') ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.integrations.datev.to')) ?></label>
                        <input type="date" name="datev_to" value="<?= e($datevDefaultTo->format('Y-m-d')) ?>" required class="<?= $inputClass('datev_to') ?>">
                    </div>
                    <button type="submit" name="action" value="download" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('settings.integrations.datev.download')) ?></button>
                    <?php if ($datevSettings->advisorEmail !== null): ?>
                        <button type="submit" name="action" value="send" title="<?= e($datevSettings->advisorEmail) ?>" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('settings.integrations.datev.send', ['email' => $datevSettings->advisorEmail])) ?></button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <?php if ($datevExports !== []): ?>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="text-slate-400 dark:text-slate-500">
                            <tr>
                                <th class="py-1 pr-4 font-medium"><?= e(t('settings.integrations.datev.log_created')) ?></th>
                                <th class="py-1 pr-4 font-medium"><?= e(t('settings.integrations.datev.log_period')) ?></th>
                                <th class="py-1 pr-4 font-medium"><?= e(t('settings.integrations.datev.log_type')) ?></th>
                                <th class="py-1 pr-4 font-medium text-right"><?= e(t('settings.integrations.datev.log_bookings')) ?></th>
                                <th class="py-1 pr-4 font-medium text-right"><?= e(t('settings.integrations.datev.log_documents')) ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600 dark:divide-slate-800 dark:text-slate-300">
                            <?php foreach ($datevExports as $export): ?>
                                <tr>
                                    <td class="py-1 pr-4 whitespace-nowrap"><?= e($export->createdAt->format('d.m.Y H:i')) ?></td>
                                    <td class="py-1 pr-4 whitespace-nowrap"><?= e($export->periodFrom->format('d.m.Y') . ' – ' . $export->periodTo->format('d.m.Y')) ?></td>
                                    <td class="py-1 pr-4"><?= e($export->trigger->label() . ($export->recipient !== null ? ' (' . $export->recipient . ')' : '')) ?></td>
                                    <td class="py-1 pr-4 text-right"><?= $export->bookingCount ?></td>
                                    <td class="py-1 pr-4 text-right"><?= $export->documentCount ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
