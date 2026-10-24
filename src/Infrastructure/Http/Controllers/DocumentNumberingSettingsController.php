<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Numbering\Command\UpdateDocumentNumberingSettings;
use App\Application\Numbering\Query\ListDocumentNumberingSettings;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class DocumentNumberingSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $entries = $this->queries->ask(new ListDocumentNumberingSettings());

        echo View::page('settings/numbering', [
            'entries' => $entries,
            'errors' => [],
        ], activeNav: 'settings-nummerierung', title: t('settings.numbering.title'));
    }

    public function update(Request $request): void
    {
        $rawEntries = $request->post['entries'] ?? [];
        $entries = is_array($rawEntries) ? $rawEntries : [];

        $command = new UpdateDocumentNumberingSettings(entries: $entries);

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.numbering.flash.saved'));
            Response::redirect('/einstellungen/nummerierung');
        } catch (ValidationException $e) {
            $overviewEntries = $this->queries->ask(new ListDocumentNumberingSettings());
            echo View::page('settings/numbering', [
                'entries' => $overviewEntries,
                'errors' => $e->errors(),
            ], activeNav: 'settings-nummerierung', title: t('settings.numbering.title'));
        }
    }
}
