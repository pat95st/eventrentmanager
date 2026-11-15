<?php
/**
 * @var \App\Domain\OnlinePayment\OnlinePaymentSettings $settings
 * @var bool $hasBaseUrl
 * @var string $webhookUrl
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$hasOld = $old !== [];
$checked = static fn (string $key, bool $current): bool => $hasOld ? (($old[$key] ?? '') === '1') : $current;
$val = static fn (string $key, ?string $current): string => $hasOld ? (string) ($old[$key] ?? '') : (string) $current;
$inputClass = static fn (string $key): string => 'mt-1 w-full rounded-md border ' . (isset($errors[$key]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700') . ' px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-800';
$error = static fn (string $key): string => isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';
$badge = static fn (bool $usable): string => $usable
    ? '<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950/40 dark:text-green-400">aktiv</span>'
    : '<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">inaktiv</span>';
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Online-Zahlung</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Kunden können versendete Rechnungen über einen persönlichen Zahlungslink per PayPal oder SumUp (Karte, Apple Pay, Google Pay) bezahlen.
        Nach erfolgreicher Zahlung wird die Rechnung automatisch als bezahlt markiert.
    </p>
</div>

<?php if (!$hasBaseUrl): ?>
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
        Es ist noch keine Basis-URL hinterlegt. Ohne sie kann der Zahlungslink nicht in Rechnungs-E-Mails eingefügt werden und SumUp kann keine Statusmeldungen senden.
        <a href="/einstellungen/integrationen" class="font-medium underline">Basis-URL unter Integrationen hinterlegen</a>
    </div>
<?php endif; ?>

<form method="post" action="/einstellungen/online-zahlung" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">

    <!-- PayPal -->
    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-1 flex items-center gap-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">PayPal</h2>
            <?= $badge($settings->isPayPalUsable()) ?>
        </div>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Benötigt eine REST-App im PayPal-Developer-Dashboard (developer.paypal.com → Apps &amp; Credentials). Client-ID und Secret der Sandbox-App funktionieren nur mit aktivierter Option „Sandbox“.
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="paypal_enabled" value="1" <?= $checked('paypal_enabled', $settings->paypalEnabled) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                PayPal als Zahlungsart anbieten
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="paypal_sandbox" value="1" <?= $checked('paypal_sandbox', $settings->paypalSandbox) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Sandbox (Testmodus, keine echten Zahlungen)
            </label>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="paypal_client_id">Client-ID</label>
                <input type="text" id="paypal_client_id" name="paypal_client_id" value="<?= e($val('paypal_client_id', $settings->paypalClientId)) ?>" autocomplete="off" class="<?= $inputClass('paypal_client_id') ?>">
                <?= $error('paypal_client_id') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="paypal_client_secret">Secret</label>
                <input type="password" id="paypal_client_secret" name="paypal_client_secret" value="" autocomplete="new-password"
                       placeholder="<?= $settings->paypalClientSecret !== null ? 'hinterlegt – leer lassen, um es zu behalten' : '' ?>" class="<?= $inputClass('paypal_client_secret') ?>">
                <?= $error('paypal_client_secret') ?>
            </div>
        </div>
    </div>

    <!-- SumUp -->
    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-1 flex items-center gap-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">SumUp</h2>
            <?= $badge($settings->isSumUpUsable()) ?>
        </div>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            API-Schlüssel unter me.sumup.com → Entwickler → API-Schlüssel erzeugen. Der Händlercode steht im SumUp-Konto unter Profil (z. B. „MH4H92C7“).
            Die Bezahlseite von SumUp (Hosted Checkout) muss für das Konto freigeschaltet sein.
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 sm:col-span-2">
                <input type="checkbox" name="sumup_enabled" value="1" <?= $checked('sumup_enabled', $settings->sumupEnabled) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                SumUp als Zahlungsart anbieten
            </label>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="sumup_api_key">API-Schlüssel</label>
                <input type="password" id="sumup_api_key" name="sumup_api_key" value="" autocomplete="new-password"
                       placeholder="<?= $settings->sumupApiKey !== null ? 'hinterlegt – leer lassen, um ihn zu behalten' : 'sup_sk_…' ?>" class="<?= $inputClass('sumup_api_key') ?>">
                <?= $error('sumup_api_key') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="sumup_merchant_code">Händlercode</label>
                <input type="text" id="sumup_merchant_code" name="sumup_merchant_code" value="<?= e($val('sumup_merchant_code', $settings->sumupMerchantCode)) ?>" autocomplete="off" class="<?= $inputClass('sumup_merchant_code') ?>">
                <?= $error('sumup_merchant_code') ?>
            </div>
            <div class="sm:col-span-2">
                <p class="text-xs text-slate-500 dark:text-slate-400">Statusmeldungen (Webhook) schickt SumUp automatisch an:</p>
                <p class="mt-1 break-all font-mono text-xs text-slate-700 dark:text-slate-300"><?= e($webhookUrl) ?></p>
            </div>
        </div>
    </div>

    <!-- Allgemein -->
    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Allgemein</h2>
        <div class="max-w-xs">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="currency">Währung</label>
            <input type="text" id="currency" name="currency" maxlength="3" value="<?= e($val('currency', $settings->currency)) ?>" class="<?= $inputClass('currency') ?> uppercase">
            <?= $error('currency') ?>
        </div>
    </div>

    <div>
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
    </div>
</form>

<?php if ($settings->paypalClientSecret !== null || $settings->sumupApiKey !== null): ?>
    <div class="mt-6 flex flex-wrap gap-4">
        <?php foreach ([
            ['paypal', 'PayPal-Secret', $settings->paypalClientSecret !== null],
            ['sumup', 'SumUp-API-Schlüssel', $settings->sumupApiKey !== null],
        ] as [$provider, $label, $present]): ?>
            <?php if ($present): ?>
                <form method="post" action="/einstellungen/online-zahlung/<?= e($provider) ?>/zugangsdaten/loeschen" data-confirm="<?= e($label . ' wirklich entfernen? Die Zahlungsart wird dabei deaktiviert.') ?>" data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e($label) ?> entfernen</button>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
