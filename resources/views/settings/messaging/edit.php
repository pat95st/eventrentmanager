<?php
/** @var \App\Domain\Messaging\MessagingSettings $settings */
/** @var array<string, string> $errors */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.messaging.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.messaging.subtitle')) ?></p>
</div>

<form method="post" action="/einstellungen/messenger" class="max-w-2xl space-y-8 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <!-- WhatsApp Business -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">WhatsApp Business (Meta Cloud API)</h2>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="whatsapp_enabled" <?= $settings->whatsappEnabled ? 'checked' : '' ?>
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                <?= e(t('settings.messaging.field.enabled')) ?>
            </label>
        </div>
        <?php if (isset($errors['whatsappPhoneNumberId']) || isset($errors['whatsappAccessToken'])): ?>
            <p class="text-xs text-red-600 dark:text-red-400"><?= e($errors['whatsappPhoneNumberId'] ?? $errors['whatsappAccessToken']) ?></p>
        <?php endif; ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="whatsapp_phone_number_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.messaging.field.whatsapp_phone_number_id')) ?></label>
                <input type="text" id="whatsapp_phone_number_id" name="whatsapp_phone_number_id" value="<?= e($settings->whatsappPhoneNumberId ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <div>
                <label for="whatsapp_business_account_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.messaging.field.whatsapp_business_account_id')) ?></label>
                <input type="text" id="whatsapp_business_account_id" name="whatsapp_business_account_id" value="<?= e($settings->whatsappBusinessAccountId ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
        </div>
        <div>
            <label for="whatsapp_access_token" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.messaging.field.whatsapp_access_token')) ?></label>
            <input type="password" id="whatsapp_access_token" name="whatsapp_access_token"
                   placeholder="<?= $settings->whatsappAccessToken !== null ? e(t('settings.messaging.field.token_placeholder')) : '' ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <p class="text-xs text-slate-400"><?= e(t('settings.messaging.field.whatsapp_hint')) ?></p>
        <button type="submit" form="test-whatsapp" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('settings.messaging.test_connection')) ?>
        </button>
    </div>

    <hr class="border-slate-100 dark:border-slate-800">

    <!-- Telegram -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Telegram</h2>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="telegram_enabled" <?= $settings->telegramEnabled ? 'checked' : '' ?>
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                <?= e(t('settings.messaging.field.enabled')) ?>
            </label>
        </div>
        <?php if (isset($errors['telegramEnabled'])): ?><p class="text-xs text-red-600 dark:text-red-400"><?= e($errors['telegramEnabled']) ?></p><?php endif; ?>
        <div>
            <label for="telegram_bot_token" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.messaging.field.telegram_bot_token')) ?></label>
            <input type="password" id="telegram_bot_token" name="telegram_bot_token"
                   placeholder="<?= $settings->telegramBotToken !== null ? e(t('settings.messaging.field.token_placeholder')) : '' ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <p class="text-xs text-slate-400"><?= e(t('settings.messaging.field.telegram_hint')) ?></p>
        <button type="submit" form="test-telegram" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('settings.messaging.test_connection')) ?>
        </button>
    </div>

    <hr class="border-slate-100 dark:border-slate-800">

    <!-- Signal -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Signal (signal-cli-rest-api)</h2>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="signal_enabled" <?= $settings->signalEnabled ? 'checked' : '' ?>
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                <?= e(t('settings.messaging.field.enabled')) ?>
            </label>
        </div>
        <?php if (isset($errors['signalEnabled'])): ?><p class="text-xs text-red-600 dark:text-red-400"><?= e($errors['signalEnabled']) ?></p><?php endif; ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="signal_api_base_url" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.messaging.field.signal_api_base_url')) ?></label>
                <input type="text" id="signal_api_base_url" name="signal_api_base_url" placeholder="http://localhost:8080" value="<?= e($settings->signalApiBaseUrl ?? '') ?>"
                       class="mt-1 w-full rounded-md border <?= isset($errors['signalApiBaseUrl']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <?php if (isset($errors['signalApiBaseUrl'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['signalApiBaseUrl']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="signal_sender_number" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.messaging.field.signal_sender_number')) ?></label>
                <input type="text" id="signal_sender_number" name="signal_sender_number" placeholder="+49..." value="<?= e($settings->signalSenderNumber ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
        </div>
        <p class="text-xs text-slate-400"><?= e(t('settings.messaging.field.signal_hint')) ?></p>
        <button type="submit" form="test-signal" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('settings.messaging.test_connection')) ?>
        </button>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="/einstellungen/messenger-vorlagen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('settings.messaging.templates_link')) ?></a>
    </div>
</form>

<!-- "Verbindung testen"-Buttons liegen je in einem EIGENEN, unsichtbaren Formular außerhalb des Haupt-Formulars,
     damit sie NICHT versehentlich die (ggf. noch ungespeicherten) Zugangsdaten des Hauptformulars mitsenden,
     sondern bewusst die zuletzt GESPEICHERTEN Zugangsdaten testen (siehe TestMessagingConnectionHandler). -->
<form id="test-whatsapp" method="post" action="/einstellungen/messenger/test/whatsapp" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
</form>
<form id="test-telegram" method="post" action="/einstellungen/messenger/test/telegram" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
</form>
<form id="test-signal" method="post" action="/einstellungen/messenger/test/signal" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
</form>
