<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
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
use App\Domain\Exception\ValidationException;
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

    /** @param array<string, string> $errors */
    private function render(array $errors): void
    {
        echo View::page('settings/integrations', [
            'baseSettings' => $this->queries->ask(new GetIntegrationBaseSettings()),
            'googleMerchantSettings' => $this->queries->ask(new GetGoogleMerchantSettings()),
            'googleBusinessSettings' => $this->queries->ask(new GetGoogleBusinessSettings()),
            'whatsappSettings' => $this->queries->ask(new GetWhatsappBusinessSettings()),
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
