<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\ClosingTextTemplate\Command\CreateClosingTextTemplate;
use App\Application\ClosingTextTemplate\Command\CreateClosingTextTemplateFolder;
use App\Application\ClosingTextTemplate\Command\DeleteClosingTextTemplate;
use App\Application\ClosingTextTemplate\Command\DeleteClosingTextTemplateFolder;
use App\Application\ClosingTextTemplate\Command\UpdateClosingTextTemplate;
use App\Application\ClosingTextTemplate\Command\UpdateClosingTextTemplateFolder;
use App\Application\ClosingTextTemplate\Query\ListClosingTextTemplateFolders;
use App\Application\ClosingTextTemplate\Query\ListClosingTextTemplates;
use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Domain\ClosingTextTemplate\ClosingTextTemplate;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolder;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Settings-CRUD-Seite für Textvorlagen (Schlusstexte) + Ordnerstruktur
 * (siehe ClosingTextTemplate(Folder)) — Aufbau analog CategoryController,
 * hier aber für ZWEI Entitäten (Ordner + Vorlagen) auf einer Seite.
 * Zusätzlich ein schlankes JSON-Listing (siehe list()) für den
 * "Vorlage einfügen"-Picker in den Dokumentformularen (Rechnung/Angebot/
 * Auftragsbestätigung), das OHNE Administration-Recht erreichbar ist (siehe
 * bootstrap.php-Routenkommentar).
 */
final class ClosingTextTemplateController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $this->render([], [], null, null);
    }

    public function editFolder(Request $request): void
    {
        $id = $request->param('id');
        $folders = $this->queries->ask(new ListClosingTextTemplateFolders());
        $editingFolder = $this->findFolder($folders, $id);
        if ($editingFolder === null) {
            Response::notFound('Dieser Ordner wurde nicht gefunden.');

            return;
        }

        $this->render([], [], $editingFolder, null);
    }

    public function editTemplate(Request $request): void
    {
        $id = $request->param('id');
        $templates = $this->queries->ask(new ListClosingTextTemplates());
        $editingTemplate = $this->findTemplate($templates, $id);
        if ($editingTemplate === null) {
            Response::notFound('Diese Textvorlage wurde nicht gefunden.');

            return;
        }

        $this->render([], [], null, $editingTemplate);
    }

    public function storeFolder(Request $request): void
    {
        $name = $request->input('name', '') ?? '';
        $parentId = $this->nullIfEmpty($request->input('parent_id'));

        try {
            $this->commands->dispatch(new CreateClosingTextTemplateFolder($name, $parentId));
            Flash::success('Ordner wurde angelegt.');
            Response::redirect('/einstellungen/textvorlagen');
        } catch (ValidationException $e) {
            $this->render($e->errors(), ['name' => $name, 'parent_id' => $parentId], null, null, isFolderForm: true);
        }
    }

    public function updateFolder(Request $request): void
    {
        $id = $request->param('id');
        $name = $request->input('name', '') ?? '';
        $parentId = $this->nullIfEmpty($request->input('parent_id'));

        try {
            $this->commands->dispatch(new UpdateClosingTextTemplateFolder($id, $name, $parentId));
            Flash::success('Ordner wurde aktualisiert.');
            Response::redirect('/einstellungen/textvorlagen');
        } catch (ValidationException $e) {
            $folders = $this->queries->ask(new ListClosingTextTemplateFolders());
            $this->render($e->errors(), ['id' => $id, 'name' => $name, 'parent_id' => $parentId], $this->findFolder($folders, $id), null, isFolderForm: true);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroyFolder(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteClosingTextTemplateFolder($id));
            Flash::success('Ordner wurde gelöscht.');
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/textvorlagen');
    }

    public function storeTemplate(Request $request): void
    {
        $name = $request->input('name', '') ?? '';
        $folderId = $this->nullIfEmpty($request->input('folder_id'));
        $content = $request->input('content');

        try {
            $this->commands->dispatch(new CreateClosingTextTemplate($name, $folderId, $content));
            Flash::success('Textvorlage wurde angelegt.');
            Response::redirect('/einstellungen/textvorlagen');
        } catch (ValidationException $e) {
            $this->render($e->errors(), ['name' => $name, 'folder_id' => $folderId, 'content' => $content ?? ''], null, null);
        }
    }

    public function updateTemplate(Request $request): void
    {
        $id = $request->param('id');
        $name = $request->input('name', '') ?? '';
        $folderId = $this->nullIfEmpty($request->input('folder_id'));
        $content = $request->input('content');

        try {
            $this->commands->dispatch(new UpdateClosingTextTemplate($id, $name, $folderId, $content));
            Flash::success('Textvorlage wurde aktualisiert.');
            Response::redirect('/einstellungen/textvorlagen');
        } catch (ValidationException $e) {
            $templates = $this->queries->ask(new ListClosingTextTemplates());
            $this->render($e->errors(), ['id' => $id, 'name' => $name, 'folder_id' => $folderId, 'content' => $content ?? ''], null, $this->findTemplate($templates, $id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroyTemplate(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteClosingTextTemplate($id));
            Flash::success('Textvorlage wurde gelöscht.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/textvorlagen');
    }

    /**
     * Schlankes JSON-Listing aller Ordner + Vorlagen (inkl. Inhalt) für den
     * "Vorlage einfügen"-Picker (siehe resources/views/invoices/form.php
     * bzw. offers/form.php, order-confirmations/form.php) — bewusst alles
     * in einem Request statt pro Ordner nachzuladen, da die zu erwartende
     * Gesamtzahl an Vorlagen klein ist.
     */
    public function list(Request $request): void
    {
        $folders = $this->queries->ask(new ListClosingTextTemplateFolders());
        $templates = $this->queries->ask(new ListClosingTextTemplates());

        Response::json([
            'folders' => array_map(
                static fn (ClosingTextTemplateFolder $folder): array => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'parentId' => $folder->parentId,
                ],
                $folders
            ),
            'templates' => array_map(
                static fn (ClosingTextTemplate $template): array => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'folderId' => $template->folderId,
                    'content' => $template->content ?? '',
                ],
                $templates
            ),
        ]);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function render(
        array $errors,
        array $old,
        ?ClosingTextTemplateFolder $editingFolder,
        ?ClosingTextTemplate $editingTemplate,
        bool $isFolderForm = false,
    ): void {
        $folders = $this->queries->ask(new ListClosingTextTemplateFolders());
        $templates = $this->queries->ask(new ListClosingTextTemplates());

        echo View::page('settings/closing_text_templates', [
            'folders' => $folders,
            'templates' => $templates,
            'editingFolder' => $editingFolder,
            'editingTemplate' => $editingTemplate,
            'isFolderForm' => $isFolderForm || $editingFolder !== null,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-textvorlagen', title: 'Textvorlagen');
    }

    /** @param ClosingTextTemplateFolder[] $folders */
    private function findFolder(array $folders, string $id): ?ClosingTextTemplateFolder
    {
        foreach ($folders as $folder) {
            if ($folder->id === $id) {
                return $folder;
            }
        }

        return null;
    }

    /** @param ClosingTextTemplate[] $templates */
    private function findTemplate(array $templates, string $id): ?ClosingTextTemplate
    {
        foreach ($templates as $template) {
            if ($template->id === $id) {
                return $template;
            }
        }

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
