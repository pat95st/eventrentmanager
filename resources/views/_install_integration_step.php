<?php
/**
 * Optional integration step of the web installer (public/install.php, step 4).
 * Every step can be saved or skipped; all settings stay editable later under
 * Einstellungen → Integrationen.
 *
 * @var string $integrationStep one of App\Infrastructure\Installer\IntegrationSetupSteps::STEPS
 * @var array<string, string|bool> $integrationValues
 * @var string $csrfToken
 */

use App\Infrastructure\Installer\IntegrationSetupSteps;

$v = static fn (string $key): string => is_string($integrationValues[$key] ?? null) ? $integrationValues[$key] : '';
$checked = static fn (string $key): string => ($integrationValues[$key] ?? false) === true ? 'checked' : '';
$storedHint = static fn (string $key): string => ($integrationValues[$key] ?? false) === true ? 'Bereits hinterlegt – leer lassen, um ihn beizubehalten' : '';
$position = (int) array_search($integrationStep, IntegrationSetupSteps::STEPS, true) + 1;
$total = count(IntegrationSetupSteps::STEPS);
$isLast = IntegrationSetupSteps::next($integrationStep) === null;
?>
<?php if ($position === 1): ?>
    <div class="install-alert install-alert-ok">
        Datenbank und Administrator-Zugang sind eingerichtet. Jetzt können optional die Integrationen konfiguriert werden –
        jeder Schritt lässt sich überspringen und später unter <code>Einstellungen&nbsp;&rarr;&nbsp;Integrationen</code> nachholen.
    </div>
<?php endif; ?>
<div class="install-substeps">Integration <?= $position ?> von <?= $total ?> · optional</div>
<h2><?= h(IntegrationSetupSteps::title($integrationStep)) ?></h2>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

    <?php if ($integrationStep === IntegrationSetupSteps::BASE_URL): ?>
        <p>Öffentliche Adresse, unter der diese Installation erreichbar ist. Sie wird für Links in E-Mails, Produkt-Feeds,
           Zahlungslinks und als OAuth-Rücksprungadresse (z. B. Google Unternehmensprofil) verwendet.</p>
        <label>Basis-URL
            <input type="text" name="base_url" value="<?= h($v('base_url')) ?>" placeholder="https://verleih.example.de">
        </label>

    <?php elseif ($integrationStep === IntegrationSetupSteps::ONLINE_PAYMENTS): ?>
        <p>Kunden können Rechnungen über einen Zahlungslink online bezahlen. Zahlungseingänge werden automatisch verbucht.</p>
        <label class="install-checkbox">
            <input type="checkbox" name="paypal_enabled" value="1" <?= $checked('paypal_enabled') ?>>
            PayPal aktivieren
        </label>
        <label class="install-checkbox">
            <input type="checkbox" name="paypal_sandbox" value="1" <?= $checked('paypal_sandbox') ?>>
            PayPal-Sandbox (Testmodus) verwenden
        </label>
        <label>PayPal Client-ID
            <input type="text" name="paypal_client_id" value="<?= h($v('paypal_client_id')) ?>" autocomplete="off">
        </label>
        <label>PayPal Secret
            <input type="password" name="paypal_client_secret" value="" autocomplete="new-password" placeholder="<?= $storedHint('has_paypal_client_secret') ?>">
        </label>
        <label class="install-checkbox">
            <input type="checkbox" name="sumup_enabled" value="1" <?= $checked('sumup_enabled') ?>>
            SumUp aktivieren
        </label>
        <label>SumUp-API-Schlüssel
            <input type="password" name="sumup_api_key" value="" autocomplete="new-password" placeholder="<?= $storedHint('has_sumup_api_key') ?>">
        </label>
        <label>SumUp-Händlercode
            <input type="text" name="sumup_merchant_code" value="<?= h($v('sumup_merchant_code')) ?>">
        </label>
        <label>Währung (ISO-Code)
            <input type="text" name="currency" value="<?= h($v('currency')) ?>" maxlength="3" placeholder="EUR">
        </label>
        <p class="install-hint">Zahlungslinks verwenden die Basis-URL aus dem vorherigen Schritt.</p>

    <?php elseif ($integrationStep === IntegrationSetupSteps::AI): ?>
        <p>Mit einem OpenAI-API-Schlüssel stehen KI-Werbebilder für Artikel, die KI-Schätzung von Zeitwerten für
           Versicherungslisten und (je nach Einstellung) die Live-Übersetzung zur Verfügung.</p>
        <label>OpenAI-API-Schlüssel
            <input type="password" name="openai_api_key" value="" autocomplete="new-password" placeholder="<?= $storedHint('has_openai_api_key') ?: 'sk-…' ?>">
        </label>
        <label>Bildmodell
            <input type="text" name="image_model" value="<?= h($v('image_model')) ?>" placeholder="<?= h(\App\Domain\Settings\AiAdImageSettings::DEFAULT_IMAGE_MODEL) ?>">
        </label>
        <p class="install-hint">Leer lassen für das Standardmodell (<?= h(\App\Domain\Settings\AiAdImageSettings::DEFAULT_IMAGE_MODEL) ?>).</p>

    <?php elseif ($integrationStep === IntegrationSetupSteps::ROUTING): ?>
        <p>Berechnet Routen, Fahrzeiten und Kilometer für Touren und Fahrten über OpenRouteService. Ohne Schlüssel werden
           Strecken nur als Luftlinie angezeigt.</p>
        <label>OpenRouteService-API-Schlüssel
            <input type="password" name="api_key" value="" autocomplete="new-password" placeholder="<?= $storedHint('has_api_key') ?>">
        </label>
        <label>Puffer zwischen Abbau + Fahrzeit und nächstem Aufbau (Minuten)
            <input type="number" name="buffer_minutes" min="0" max="600" value="<?= h($v('buffer_minutes')) ?>">
        </label>
        <p class="install-hint">Den kostenlosen Schlüssel gibt es nach einer Registrierung bei openrouteservice.org.
           Die Verbindung lässt sich nach der Anmeldung unter <code>Einstellungen&nbsp;&rarr;&nbsp;Routenplanung</code> testen.</p>

    <?php elseif ($integrationStep === IntegrationSetupSteps::GOOGLE_MERCHANT): ?>
        <p>Veröffentlicht Mietartikel als Produkte im Google Merchant Center (Google Shopping).</p>
        <label>Merchant-ID
            <input type="text" name="merchant_id" value="<?= h($v('merchant_id')) ?>" placeholder="z. B. 123456789">
        </label>
        <label>Zielland (ISO-Code)
            <input type="text" name="target_country" value="<?= h($v('target_country')) ?>" maxlength="2" placeholder="DE">
        </label>
        <label>Inhaltssprache
            <input type="text" name="content_language" value="<?= h($v('content_language')) ?>" maxlength="5" placeholder="de">
        </label>
        <label>Produkt-Link-Vorlage (optional)
            <input type="text" name="product_link_template" value="<?= h($v('product_link_template')) ?>" placeholder="https://meine-website.de/produkte/{id}">
        </label>
        <p class="install-hint">Die Service-Account-Datei (JSON) für den API-Zugriff wird nach der Anmeldung unter
           <code>Einstellungen&nbsp;&rarr;&nbsp;Integrationen</code> hochgeladen.</p>

    <?php elseif ($integrationStep === IntegrationSetupSteps::GOOGLE_BUSINESS): ?>
        <p>Veröffentlicht Mietartikel im Google Unternehmensprofil. Dafür werden OAuth-Zugangsdaten aus der Google Cloud Console benötigt.</p>
        <label>Client-ID
            <input type="text" name="client_id" value="<?= h($v('client_id')) ?>" autocomplete="off">
        </label>
        <label>Client-Secret
            <input type="password" name="client_secret" value="" autocomplete="new-password"
                   placeholder="<?= ($integrationValues['has_client_secret'] ?? false) ? 'Bereits hinterlegt – leer lassen, um es beizubehalten' : '' ?>">
        </label>
        <p class="install-hint">Die eigentliche Verbindung mit dem Google-Konto erfolgt nach der Anmeldung unter
           <code>Einstellungen&nbsp;&rarr;&nbsp;Integrationen</code> („Mit Google verbinden“).</p>

    <?php elseif ($integrationStep === IntegrationSetupSteps::WHATSAPP): ?>
        <p>Veröffentlicht Mietartikel im Produktkatalog von WhatsApp Business (Meta Commerce Manager).</p>
        <label>Zugriffstoken
            <input type="password" name="access_token" value="" autocomplete="new-password"
                   placeholder="<?= ($integrationValues['has_access_token'] ?? false) ? 'Bereits hinterlegt – leer lassen, um es beizubehalten' : '' ?>">
        </label>
        <label>Katalog-ID
            <input type="text" name="catalog_id" value="<?= h($v('catalog_id')) ?>">
        </label>
        <label>Währung (ISO-Code)
            <input type="text" name="currency" value="<?= h($v('currency')) ?>" maxlength="3" placeholder="EUR">
        </label>

    <?php elseif ($integrationStep === IntegrationSetupSteps::DATEV): ?>
        <p>Erzeugt Buchungsstapel im DATEV-Format für den Steuerberater. Kontenzuordnung, Belegoptionen und automatischer
           Versand lassen sich später unter <code>Einstellungen&nbsp;&rarr;&nbsp;Integrationen</code> verfeinern.</p>
        <label class="install-checkbox">
            <input type="checkbox" name="datev_enabled" value="1" <?= $checked('datev_enabled') ?>>
            DATEV-Export aktivieren
        </label>
        <label>Beraternummer
            <input type="text" inputmode="numeric" name="datev_consultant_number" value="<?= h($v('datev_consultant_number')) ?>" placeholder="z. B. 29098">
        </label>
        <label>Mandantennummer
            <input type="text" inputmode="numeric" name="datev_client_number" value="<?= h($v('datev_client_number')) ?>" placeholder="z. B. 55003">
        </label>
        <label>Kontenrahmen
            <select name="datev_chart_of_accounts">
                <?php foreach (\App\Domain\Datev\DatevChartOfAccounts::cases() as $chart): ?>
                    <option value="<?= h($chart->value) ?>" <?= $v('datev_chart_of_accounts') === $chart->value ? 'selected' : '' ?>><?= h($chart->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Sachkontenlänge
            <select name="datev_account_length">
                <?php for ($length = 4; $length <= 8; $length++): ?>
                    <option value="<?= $length ?>" <?= $v('datev_account_length') === (string) $length ? 'selected' : '' ?>><?= $length ?></option>
                <?php endfor; ?>
            </select>
        </label>
        <label>Beginn des Wirtschaftsjahres
            <select name="datev_fiscal_year_start_month">
                <?php foreach (['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'] as $index => $month): ?>
                    <option value="<?= $index + 1 ?>" <?= $v('datev_fiscal_year_start_month') === (string) ($index + 1) ? 'selected' : '' ?>><?= h($month) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>E-Mail-Adresse des Steuerberaters (optional)
            <input type="email" name="datev_advisor_email" value="<?= h($v('datev_advisor_email')) ?>" placeholder="kanzlei@steuerberater.de">
        </label>
    <?php endif; ?>

    <div class="install-actions">
        <button class="install-btn" type="submit" name="action" value="save_integration">
            <?= $isLast ? 'Speichern &amp; Installation abschließen' : 'Speichern &amp; weiter' ?>
        </button>
        <button class="install-btn install-btn-secondary" type="submit" name="action" value="skip_integration" formnovalidate>
            Überspringen
        </button>
        <?php if (!$isLast): ?>
            <button class="install-btn-link" type="submit" name="action" value="skip_all_integrations" formnovalidate>
                Alle weiteren Integrationen überspringen
            </button>
        <?php endif; ?>
    </div>
</form>
