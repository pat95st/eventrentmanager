<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Datev\Command\CreateDatevExport;
use App\Application\Datev\Command\UpdateDatevSettings;
use App\Application\Datev\DatevExportPackage;
use App\Application\Datev\Query\GetDatevSettings;
use App\Application\Datev\Query\ListDatevExports;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\CompleteGoogleBusinessOAuth;
use App\Application\Settings\Command\DeleteGoogleMerchantServiceAccount;
use App\Application\Settings\Command\DisconnectGoogleBusiness;
use App\Application\Settings\Command\UpdateGoogleBusinessClientCredentials;
use App\Application\Settings\Command\UpdateGoogleMerchantSettings;
use App\Application\Settings\Command\UpdateIntegrationBaseSettings;
use App\Application\Settings\Command\UpdateWhatsappBusinessSettings;
use App\Application\Settings\Command\UploadGoogleMerchantServiceAccount;
use App\Application\Settings\Query\GetGoogleBusinessSettings;
use App\Application\Settings\Query\GetGoogleMerchantSettings;
use App\Application\Settings\Query\GetIntegrationBaseSettings;
use App\Application\Settings\Query\GetWhatsappBusinessSettings;
use App\Domain\Datev\DatevAccountRole;
use App\Domain\Datev\DatevExportTrigger;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Mail\MailerException;
use DateTimeImmutable;
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Infrastructure\Integration\Google\GoogleBusinessApiClient;

final class IntegrationSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly GoogleBusinessApiClient $googleBusinessApiClient,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render([]);
    }

    public function updateBase(Request $request): void
    {
        try {
            $this->commands->dispatch(new UpdateIntegrationBaseSettings($request->input('base_url')));
            Flash::success('Basis-URL wurde gespeichert.');
        } catch (ValidationException $e) {
            $this->render($e->errors());

            return;
        }

        Response::redirect('/einstellungen/integrationen');
    }

    public function updateGoogleMerchant(Request $request): void
    {
        try {
            $this->commands->dispatch(new UpdateGoogleMerchantSettings(
                merchantId: $request->input('merchant_id'),
                targetCountry: $request->input('target_country', 'DE'),
                contentLanguage: $request->input('content_language', 'de'),
                productLinkTemplate: $request->input('product_link_template'),
            ));
            Flash::success('Google-Merchant-Einstellungen wurden gespeichert.');
        } catch (ValidationException $e) {
            $this->render($e->errors());

            return;
        }

        Response::redirect('/einstellungen/integrationen');
    }

    public function uploadGoogleMerchantServiceAccount(Request $request): void
    {
        $file = $request->file('service_account_json');

        if ($file !== null) {
            try {
                $this->commands->dispatch(new UploadGoogleMerchantServiceAccount($file));
                Flash::success('Service-Account-Datei wurde hinterlegt.');
            } catch (ValidationException $e) {
                Flash::error(implode(' ', $e->errors()));
            }
        }

        Response::redirect('/einstellungen/integrationen');
    }

    public function deleteGoogleMerchantServiceAccount(Request $request): void
    {
        $this->commands->dispatch(new DeleteGoogleMerchantServiceAccount());
        Flash::success('Service-Account-Datei wurde entfernt.');
        Response::redirect('/einstellungen/integrationen');
    }

    public function updateGoogleBusinessCredentials(Request $request): void
    {
        try {
            $this->commands->dispatch(new UpdateGoogleBusinessClientCredentials(
                clientId: $request->input('client_id'),
                clientSecret: $request->input('client_secret'),
            ));
            Flash::success('OAuth-Zugangsdaten wurden gespeichert.');
        } catch (ValidationException $e) {
            $this->render($e->errors());

            return;
        }

        Response::redirect('/einstellungen/integrationen');
    }

    /** Leitet zur Google-OAuth-Einwilligungsseite weiter, siehe GoogleBusinessApiClient::connectUrl(). */
    public function connectGoogleBusiness(Request $request): void
    {
        $settings = $this->queries->ask(new GetGoogleBusinessSettings());
        if ($settings->clientId === null) {
            Flash::error('Bitte zuerst Client-ID und Client-Secret hinterlegen.');
            Response::redirect('/einstellungen/integrationen');

            return;
        }

        $url = $this->googleBusinessApiClient->connectUrl($settings->clientId, $this->redirectUri($request), Csrf::token());

        Response::redirect($url);
    }

    /** OAuth-Rücksprung von Google (siehe connectGoogleBusiness()). */
    public function googleBusinessCallback(Request $request): void
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        if ($error !== null) {
            Flash::error('Die Verbindung mit Google wurde abgebrochen (' . $error . ').');
            Response::redirect('/einstellungen/integrationen');

            return;
        }

        if ($code === null || $state === null || !Csrf::verify($state)) {
            Flash::error('Die Antwort von Google konnte nicht verifiziert werden. Bitte erneut versuchen.');
            Response::redirect('/einstellungen/integrationen');

            return;
        }

        try {
            $this->commands->dispatch(new CompleteGoogleBusinessOAuth($code, $this->redirectUri($request)));
            Flash::success('Google Unternehmensprofil wurde verbunden.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }

        Response::redirect('/einstellungen/integrationen');
    }

    public function disconnectGoogleBusiness(Request $request): void
    {
        $this->commands->dispatch(new DisconnectGoogleBusiness());
        Flash::success('Verbindung zum Google Unternehmensprofil wurde getrennt.');
        Response::redirect('/einstellungen/integrationen');
    }

    public function updateWhatsapp(Request $request): void
    {
        try {
            $this->commands->dispatch(new UpdateWhatsappBusinessSettings(
                accessToken: $request->input('access_token'),
                catalogId: $request->input('catalog_id'),
                currency: $request->input('currency', 'EUR'),
            ));
            Flash::success('WhatsApp-Einstellungen wurden gespeichert.');
        } catch (ValidationException $e) {
            $this->render($e->errors());

            return;
        }

        Response::redirect('/einstellungen/integrationen');
    }

    public function updateDatev(Request $request): void
    {
        $accounts = [];
        foreach (DatevAccountRole::cases() as $role) {
            $accounts[$role->value] = $request->input('datev_account_' . $role->value);
        }

        try {
            $this->commands->dispatch(new UpdateDatevSettings(
                enabled: $request->input('datev_enabled') !== null,
                consultantNumber: $request->input('datev_consultant_number'),
                clientNumber: $request->input('datev_client_number'),
                chartOfAccounts: $request->input('datev_chart_of_accounts'),
                accountLength: $request->input('datev_account_length'),
                fiscalYearStartMonth: $request->input('datev_fiscal_year_start_month'),
                debtorMode: $request->input('datev_debtor_mode'),
                debtorAccount: $request->input('datev_debtor_account'),
                creditorAccount: $request->input('datev_creditor_account'),
                accounts: $accounts,
                receiptTaxKey: $request->input('datev_receipt_tax_key'),
                includeReceipts: $request->input('datev_include_receipts') !== null,
                includeDocuments: $request->input('datev_include_documents') !== null,
                advisorEmail: $request->input('datev_advisor_email'),
                autoSendEnabled: $request->input('datev_auto_send_enabled') !== null,
                interval: $request->input('datev_interval'),
                sendDay: $request->input('datev_send_day'),
            ));
            Flash::success('DATEV-Einstellungen wurden gespeichert.');
        } catch (ValidationException $e) {
            $this->render($e->errors(), $this->datevOldInput($request));

            return;
        }

        Response::redirect('/einstellungen/integrationen#datev');
    }

    /** Erzeugt den DATEV-Export für einen Zeitraum: Download (action=download) oder Versand an den Steuerberater (action=send). */
    public function exportDatev(Request $request): void
    {
        $from = $this->parseDate($request->input('datev_from'));
        $to = $this->parseDate($request->input('datev_to'));
        if ($from === null || $to === null) {
            Flash::error('Bitte einen gültigen Zeitraum für den DATEV-Export angeben.');
            Response::redirect('/einstellungen/integrationen#datev');

            return;
        }

        $send = $request->input('action') === 'send';

        try {
            $package = $this->commands->dispatch(new CreateDatevExport(
                $from,
                $to,
                $send ? DatevExportTrigger::ManualEmail : DatevExportTrigger::Download,
            ));
            assert($package instanceof DatevExportPackage);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/einstellungen/integrationen#datev');

            return;
        } catch (MailerException $e) {
            Flash::error('Der DATEV-Export konnte nicht per E-Mail versendet werden: ' . $e->getMessage());
            Response::redirect('/einstellungen/integrationen#datev');

            return;
        }

        if (!$send) {
            Response::download($package->zipContent, $package->filename, 'application/zip');

            return;
        }

        $message = 'DATEV-Export (' . $package->bookingCount() . ' Buchungen, ' . $package->documentCount . ' Belege) wurde an den Steuerberater gesendet.';
        if ($package->warnings !== []) {
            Flash::error($message . ' Hinweise: ' . implode(' ', $package->warnings));
        } else {
            Flash::success($message);
        }
        Response::redirect('/einstellungen/integrationen#datev');
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $value));

        return $date !== false ? $date : null;
    }

    /** @return array<string, string|null> */
    private function datevOldInput(Request $request): array
    {
        $keys = [
            'datev_enabled', 'datev_consultant_number', 'datev_client_number', 'datev_chart_of_accounts', 'datev_account_length',
            'datev_fiscal_year_start_month', 'datev_debtor_mode', 'datev_debtor_account', 'datev_creditor_account',
            'datev_receipt_tax_key', 'datev_include_receipts', 'datev_include_documents', 'datev_advisor_email',
            'datev_auto_send_enabled', 'datev_interval', 'datev_send_day',
        ];
        foreach (DatevAccountRole::cases() as $role) {
            $keys[] = 'datev_account_' . $role->value;
        }

        $old = ['_submitted' => '1'];
        foreach ($keys as $key) {
            $old[$key] = $request->input($key);
        }

        return $old;
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string|null> $datevOld Formulareingaben bei Validierungsfehlern im DATEV-Formular
     */
    private function render(array $errors, array $datevOld = []): void
    {
        echo View::page('settings/integrations', [
            'baseSettings' => $this->queries->ask(new GetIntegrationBaseSettings()),
            'googleMerchantSettings' => $this->queries->ask(new GetGoogleMerchantSettings()),
            'googleBusinessSettings' => $this->queries->ask(new GetGoogleBusinessSettings()),
            'whatsappSettings' => $this->queries->ask(new GetWhatsappBusinessSettings()),
            'datevSettings' => $this->queries->ask(new GetDatevSettings()),
            'datevExports' => $this->queries->ask(new ListDatevExports(10)),
            'datevOld' => $datevOld,
            'errors' => $errors,
        ], activeNav: 'settings-integrationen', title: 'Integrationen');
    }

    private function redirectUri(Request $request): string
    {
        $baseSettings = $this->queries->ask(new GetIntegrationBaseSettings());
        $baseUrl = $baseSettings->baseUrl !== null ? rtrim($baseSettings->baseUrl, '/') : $this->guessBaseUrl($request);

        return $baseUrl . '/einstellungen/integrationen/google-business/callback';
    }

    /** Fallback, solange noch keine Basis-URL in den Einstellungen hinterlegt ist. */
    private function guessBaseUrl(Request $request): string
    {
        $scheme = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }
}
