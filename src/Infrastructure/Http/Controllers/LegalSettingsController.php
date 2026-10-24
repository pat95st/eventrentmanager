<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateLegalSettings;
use App\Application\Settings\Query\GetLegalSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class LegalSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetLegalSettings());

        echo View::page('settings/legal', [
            'settings' => $settings,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-rechtliches', title: t('settings.legal.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdateLegalSettings(
            rentalTermsMarkdown: $request->input('rental_terms_markdown'),
            imprintMode: $request->input('imprint_mode'),
            imprintMarkdown: $request->input('imprint_markdown'),
            imprintUrl: $request->input('imprint_url'),
            privacyMode: $request->input('privacy_mode'),
            privacyMarkdown: $request->input('privacy_markdown'),
            privacyUrl: $request->input('privacy_url'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.legal.flash.saved'));
            Response::redirect('/einstellungen/rechtliches');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetLegalSettings());
            echo View::page('settings/legal', [
                'settings' => $settings,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: 'settings-rechtliches', title: t('settings.legal.title'));
        }
    }
}
