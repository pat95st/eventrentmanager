<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateDocumentIntroTexts;
use App\Application\Settings\Query\GetDocumentIntroTexts;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/** Einstellungen › Starttexte je Dokumenttyp (Migration 113). */
final class DocumentIntroTextSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render($this->queries->ask(new GetDocumentIntroTexts()), []);
    }

    public function update(Request $request): void
    {
        $texts = [
            'offer' => $request->input('offer', '') ?? '',
            'order_confirmation' => $request->input('order_confirmation', '') ?? '',
            'invoice' => $request->input('invoice', '') ?? '',
        ];

        try {
            $this->commands->dispatch(new UpdateDocumentIntroTexts($texts));
            Flash::success(t('settings.intro_texts.flash.saved'));
            Response::redirect('/einstellungen/starttexte');
        } catch (ValidationException $e) {
            $this->render($texts, $e->errors());
        }
    }

    /** @param array<string, string> $texts @param array<string, string> $errors */
    private function render(array $texts, array $errors): void
    {
        echo View::page('settings/intro-texts', [
            'texts' => $texts,
            'errors' => $errors,
        ], activeNav: 'settings-starttexte', title: t('settings.intro_texts.title'));
    }
}
