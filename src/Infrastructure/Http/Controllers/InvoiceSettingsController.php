<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateInvoiceSettings;
use App\Application\Settings\Query\GetInvoiceSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class InvoiceSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetInvoiceSettings());

        echo View::page('settings/invoice-settings', [
            'settings' => $settings,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-rechnung', title: t('settings.invoice_settings.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdateInvoiceSettings(
            isSmallBusiness: $request->input('is_small_business') === '1',
            vatRatePercent: $request->input('vat_rate_percent', '') ?? '',
            taxNumber: $request->input('tax_number'),
            vatId: $request->input('vat_id'),
            paymentTermDays: $request->input('payment_term_days', '') ?? '',
            bankName: $request->input('bank_name'),
            iban: $request->input('iban'),
            bic: $request->input('bic'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.invoice_settings.flash.saved'));
            Response::redirect('/einstellungen/rechnung');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetInvoiceSettings());
            echo View::page('settings/invoice-settings', [
                'settings' => $settings,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: 'settings-rechnung', title: t('settings.invoice_settings.title'));
        }
    }
}
