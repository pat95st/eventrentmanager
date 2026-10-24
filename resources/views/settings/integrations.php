<?php
/**
 * @var \App\Domain\Settings\IntegrationBaseSettings $baseSettings
 * @var \App\Domain\Settings\GoogleMerchantSettings $googleMerchantSettings
 * @var \App\Domain\Settings\GoogleBusinessSettings $googleBusinessSettings
 * @var \App\Domain\Settings\WhatsappBusinessSettings $whatsappSettings
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
</div>
