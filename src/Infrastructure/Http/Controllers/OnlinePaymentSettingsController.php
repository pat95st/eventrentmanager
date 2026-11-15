<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\OnlinePayment\Command\RemoveOnlinePaymentSecret;
use App\Application\OnlinePayment\Command\UpdateOnlinePaymentSettings;
use App\Application\OnlinePayment\InvoicePaymentUrlBuilder;
use App\Application\OnlinePayment\Query\GetOnlinePaymentSettings;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ValidationException;
use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Origin;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Settings > Online-Zahlung: PayPal and SumUp credentials (migration 163). */
final class OnlinePaymentSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly InvoicePaymentUrlBuilder $urls,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render([], []);
    }

    public function update(Request $request): void
    {
        try {
            $this->commands->dispatch(new UpdateOnlinePaymentSettings(
                paypalEnabled: $request->input('paypal_enabled') === '1',
                paypalSandbox: $request->input('paypal_sandbox') === '1',
                paypalClientId: $request->input('paypal_client_id'),
                paypalClientSecret: $request->input('paypal_client_secret'),
                sumupEnabled: $request->input('sumup_enabled') === '1',
                sumupApiKey: $request->input('sumup_api_key'),
                sumupMerchantCode: $request->input('sumup_merchant_code'),
                currency: $request->input('currency', 'EUR'),
            ));
        } catch (ValidationException $e) {
            $this->render($e->errors(), $request->post);

            return;
        }

        Flash::success('Einstellungen für Online-Zahlungen wurden gespeichert.');
        Response::redirect('/einstellungen/online-zahlung');
    }

    public function removeSecret(Request $request): void
    {
        $provider = OnlinePaymentProvider::tryFrom($request->param('provider'));
        if ($provider !== null) {
            $this->commands->dispatch(new RemoveOnlinePaymentSecret($provider));
            Flash::success('Zugangsdaten für ' . $provider->shortLabel() . ' wurden entfernt.');
        }

        Response::redirect('/einstellungen/online-zahlung');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function render(array $errors, array $old): void
    {
        echo View::page('settings/online-payments', [
            'settings' => $this->queries->ask(new GetOnlinePaymentSettings()),
            'hasBaseUrl' => $this->urls->hasConfiguredBaseUrl(),
            'webhookUrl' => $this->urls->sumUpWebhookUrl(Origin::current()),
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-online-zahlung', title: 'Online-Zahlung');
    }
}
