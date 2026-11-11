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

<form method="post" action="/einstellungen/rechnung" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
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

    <?php
    $field = function (string $name, string $label, ?string $value, string $errorKey = '', string $placeholder = '', string $hint = '') use ($val, $errors): void {
        $hasError = $errorKey !== '' && isset($errors[$errorKey]);
        ?>
        <div>
            <label for="<?= e($name) ?>" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e($label) ?></label>
            <input type="text" id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($val($name, $value)) ?>" placeholder="<?= e($placeholder) ?>"
                   class="mt-1 w-full rounded-md border <?= $hasError ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if ($hasError): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors[$errorKey]) ?></p><?php elseif ($hint !== ''): ?><p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e($hint) ?></p><?php endif; ?>
        </div>
        <?php
    };
    ?>
    <fieldset class="space-y-4 border-t border-slate-100 pt-4 dark:border-slate-800">
        <legend class="text-sm font-semibold text-slate-800 dark:text-slate-200">Steuerdaten</legend>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <?php $field('tax_number', t('settings.invoice_settings.field.tax_number'), $settings->taxNumber, 'taxNumber', 'z. B. 241/123/45678'); ?>
            <?php $field('vat_id', t('settings.invoice_settings.field.vat_id'), $settings->vatId, 'vatId', 'z. B. DE123456789'); ?>
            <?php $field('tax_office', 'Finanzamt', $settings->taxOffice, '', 'z. B. Finanzamt Nürnberg-Süd', 'nur zur Ablage, erscheint nicht auf Dokumenten'); ?>
        </div>
        <p class="text-xs text-slate-400 dark:text-slate-500">Ohne Kleinunternehmerregelung muss auf Rechnungen die Steuernummer oder die USt-IdNr. stehen (§ 14 UStG). Beides erscheint in der Fußzeile der Dokumente.</p>
    </fieldset>

    <fieldset class="space-y-4 border-t border-slate-100 pt-4 dark:border-slate-800">
        <legend class="text-sm font-semibold text-slate-800 dark:text-slate-200">Firmendaten für Geschäftsbriefe</legend>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <?php $field('legal_form', 'Rechtsform', $settings->legalForm, '', 'z. B. GmbH, UG (haftungsbeschränkt), e. K.', 'wird in der Fußzeile an den Firmennamen angehängt, falls nicht schon enthalten'); ?>
            <?php $field('managing_directors', 'Geschäftsführung / Inhaber', $settings->managingDirectors, '', 'z. B. Max Mustermann'); ?>
            <?php $field('register_court', 'Registergericht', $settings->registerCourt, '', 'z. B. Amtsgericht Nürnberg'); ?>
            <?php $field('register_number', 'Registernummer', $settings->registerNumber, '', 'z. B. HRB 12345'); ?>
        </div>
        <p class="text-xs text-slate-400 dark:text-slate-500">Leere Felder erscheinen nicht. Firmenname und Anschrift pflegst du unter <a href="/einstellungen/firma" class="underline">Firma</a>.</p>
    </fieldset>

    <fieldset class="space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
        <legend class="text-sm font-semibold text-slate-800 dark:text-slate-200">Hinweis zur Umsatzsteuerbefreiung</legend>
        <label for="small_business_note" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Text auf Rechnungen, Angeboten, Auftragsbestätigungen, Rechnungskorrekturen und in der E-Rechnung</label>
        <textarea id="small_business_note" name="small_business_note" rows="2" maxlength="500" placeholder="<?= e(\App\Domain\Settings\InvoiceSettings::DEFAULT_SMALL_BUSINESS_NOTE) ?>"
                  class="mt-1 w-full rounded-md border <?= isset($errors['smallBusinessNote']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"><?= e($val('small_business_note', $settings->smallBusinessNote)) ?></textarea>
        <?php if (isset($errors['smallBusinessNote'])): ?><p class="text-xs text-red-600 dark:text-red-400"><?= e($errors['smallBusinessNote']) ?></p><?php endif; ?>
        <p class="text-xs text-slate-400 dark:text-slate-500">Leer lassen für den Standardtext „<?= e(\App\Domain\Settings\InvoiceSettings::DEFAULT_SMALL_BUSINESS_NOTE) ?>“. Wird nur bei aktiver Kleinunternehmerregelung gedruckt.</p>
    </fieldset>

    <p class="text-xs text-slate-400 dark:text-slate-500"><?= t('settings.invoice_settings.numbering_hint', ['link' => '<a href="/einstellungen/nummerierung" class="underline">' . e(t('nav.numbering_settings')) . '</a>']) ?></p>

    <p class="border-t border-slate-100 pt-4 text-xs text-slate-400 dark:border-slate-800 dark:text-slate-500">
        <?= e(t('settings.invoice_settings.moved_hint')) ?>
        <a href="/einstellungen/bankverbindungen" class="text-brand-700 hover:underline dark:text-brand-400"><?= e(t('nav.bank_accounts')) ?></a>,
        <a href="/einstellungen/zahlungsarten" class="text-brand-700 hover:underline dark:text-brand-400"><?= e(t('nav.payment_methods')) ?></a>
    </p>

    <div class="form-actions flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>
