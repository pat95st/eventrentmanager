<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateOfferPdfSettings;
use App\Application\Settings\Query\GetOfferPdfSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class OfferPdfTemplateSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetOfferPdfSettings());

        echo View::page('settings/offer-pdf-template', [
            'settings' => $settings,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-angebotsvorlage', title: t('settings.offer_pdf_template.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdateOfferPdfSettings(
            introHtml: $request->input('intro_html'),
            closingHtml: $request->input('closing_html'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.offer_pdf_template.flash.saved'));
            Response::redirect('/einstellungen/angebotsvorlage');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetOfferPdfSettings());
            echo View::page('settings/offer-pdf-template', [
                'settings' => $settings,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: 'settings-angebotsvorlage', title: t('settings.offer_pdf_template.title'));
        }
    }
}
