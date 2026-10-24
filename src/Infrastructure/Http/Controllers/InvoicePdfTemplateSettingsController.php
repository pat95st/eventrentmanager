<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateInvoicePdfSettings;
use App\Application\Settings\Query\GetInvoicePdfSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class InvoicePdfTemplateSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetInvoicePdfSettings());

        echo View::page('settings/invoice-pdf-template', [
            'settings' => $settings,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-rechnungsvorlage', title: t('settings.invoice_pdf_template.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdateInvoicePdfSettings(
            introHtml: $request->input('intro_html'),
            closingHtml: $request->input('closing_html'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.invoice_pdf_template.flash.saved'));
            Response::redirect('/einstellungen/rechnungsvorlage');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetInvoicePdfSettings());
            echo View::page('settings/invoice-pdf-template', [
                'settings' => $settings,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: 'settings-rechnungsvorlage', title: t('settings.invoice_pdf_template.title'));
        }
    }
}
