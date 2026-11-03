<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\CaseItem\Command\AddCaseContentItem;
use App\Application\CaseItem\Command\AddCaseInventoryNumber;
use App\Application\CaseItem\Command\CreateCaseItem;
use App\Application\CaseItem\Command\DeleteCaseContentItem;
use App\Application\CaseItem\Command\DeleteCaseInventoryNumber;
use App\Application\CaseItem\Command\DeleteCaseItem;
use App\Application\CaseItem\Command\UpdateCaseContentItem;
use App\Application\CaseItem\Command\UpdateCaseInventoryNumber;
use App\Application\CaseItem\Command\UpdateCaseItem;
use App\Application\CaseItem\Query\GetCaseItem;
use App\Application\CaseItem\Query\ListCaseItems;
use App\Application\Article\Query\ListArticles;
use App\Application\Command\CommandBus;
use App\Application\Manufacturer\Query\ListManufacturers;
use App\Application\Query\QueryBus;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\CaseItem\CaseItem;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class CaseController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        // Migration 114: Fotos, Hinweistext und Stückliste je Case-Exemplar.
        private readonly ?\App\Infrastructure\FileStorage\LocalFileStorage $fileStorage = null,
        private readonly ?\App\Infrastructure\Pdf\CasePackingListPdfGenerator $packingListPdf = null,
    ) {
    }

    /** Hinweistext (WYSIWYG) eines Exemplars speichern. */
    public function updateNotice(Request $request): void
    {
        $inventoryNumberId = $request->param('id');
        $caseId = $request->input('case_id', '') ?? '';

        try {
            $this->commands->dispatch(new \App\Application\CaseItem\Command\UpdateCaseInventoryNotice($inventoryNumberId, $request->input('notice_html')));
            Flash::success('Hinweistext wurde gespeichert.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/cases/' . $caseId . '#exemplar-' . $inventoryNumberId);
    }

    /** Ein oder mehrere Fotos des befüllten Exemplars hochladen. */
    public function addImages(Request $request): void
    {
        $inventoryNumberId = $request->param('id');
        $caseId = $request->input('case_id', '') ?? '';

        $files = $request->fileList('images');
        if ($files === [] && ($single = $request->file('images')) !== null) {
            $files = [$single];
        }
        if ($files === []) {
            Flash::error('Bitte mindestens ein Bild auswählen.');
            Response::redirect('/cases/' . $caseId . '#exemplar-' . $inventoryNumberId);

            return;
        }

        $added = 0;
        $errors = [];
        foreach ($files as $file) {
            try {
                $this->commands->dispatch(new \App\Application\CaseItem\Command\AddCaseInventoryNumberImage($inventoryNumberId, $file));
                $added++;
            } catch (ValidationException $e) {
                $errors[] = $file['name'] . ': ' . implode(' ', $e->errors());
            } catch (NotFoundException $e) {
                Response::notFound($e->getMessage());

                return;
            }
        }

        if ($added > 0) {
            Flash::success($added === 1 ? 'Bild wurde hinzugefügt.' : "{$added} Bilder wurden hinzugefügt.");
        }
        if ($errors !== []) {
            Flash::error(implode(' ', $errors));
        }
        Response::redirect('/cases/' . $caseId . '#exemplar-' . $inventoryNumberId);
    }

    public function deleteImage(Request $request): void
    {
        $caseId = $request->input('case_id', '') ?? '';
        $inventoryNumberId = $request->input('inventory_number_id', '') ?? '';

        try {
            $this->commands->dispatch(new \App\Application\CaseItem\Command\DeleteCaseInventoryNumberImage($request->param('id')));
            Flash::success('Bild wurde entfernt.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/cases/' . $caseId . ($inventoryNumberId !== '' ? '#exemplar-' . $inventoryNumberId : ''));
    }

    /** Liefert ein Exemplar-Foto aus: /cases/{id}/bild?image=… */
    public function showImage(Request $request): void
    {
        $imageId = (string) $request->query('image', '');
        $image = null;
        try {
            $detail = $this->queries->ask(new GetCaseItem($request->param('id')));
            foreach ($detail->inventoryNumbers as $entry) {
                foreach ($entry->images as $candidate) {
                    if ($candidate->id === $imageId) {
                        $image = $candidate;
                    }
                }
            }
        } catch (NotFoundException) {
        }

        if ($image === null || $this->fileStorage === null) {
            Response::notFound('Bild wurde nicht gefunden.');

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('cases', $image->filePath);
        if (!is_file($absolutePath)) {
            Response::notFound('Bild wurde nicht gefunden.');

            return;
        }
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'application/octet-stream') : 'application/octet-stream';
        Response::file($absolutePath, basename($image->filePath), $mimeType);
    }

    /** Stückliste eines Exemplars als PDF: /cases/{id}/stueckliste.pdf?exemplar=… */
    public function packingListPdf(Request $request): void
    {
        try {
            $detail = $this->queries->ask(new GetCaseItem($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $exemplarId = (string) $request->query('exemplar', '');
        $entry = null;
        foreach ($detail->inventoryNumbers as $candidate) {
            if ($candidate->inventoryNumber->id === $exemplarId) {
                $entry = $candidate;
            }
        }
        if ($entry === null || $this->packingListPdf === null) {
            Response::notFound('Case-Exemplar wurde nicht gefunden.');

            return;
        }

        $companySettings = $this->queries->ask(new \App\Application\Settings\Query\GetCompanySettings());
        $pdf = $this->packingListPdf->generate($detail, $entry, $companySettings);
        $filename = 'Stueckliste-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $entry->inventoryNumber->number) . '.pdf';
        Response::raw($pdf, 200, 'application/pdf', ['Content-Disposition' => 'inline; filename="' . $filename . '"']);
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $manufacturerId = $this->nullIfEmpty($request->query('hersteller'));
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListCaseItems($search, $manufacturerId, $page, $pageSize));
        $manufacturers = $this->queries->ask(new ListManufacturers());

        $viewData = [
            'cases' => $result->items,
            'manufacturers' => $manufacturers,
            'search' => $search ?? '',
            'selectedManufacturerId' => $manufacturerId,
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/cases',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
                'hersteller' => $manufacturerId ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('cases/_table', $viewData);

            return;
        }

        echo View::page('cases/index', $viewData, activeNav: 'cases', title: 'Cases');
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateCaseItem(
            name: $request->input('name', '') ?? '',
            manufacturerId: $request->input('manufacturer_id', '') ?? '',
            description: $request->input('description'),
        );

        try {
            $caseId = $this->commands->dispatch($command);
            Flash::success('Case wurde angelegt.');
            Response::redirect('/cases/' . $caseId);
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetCaseItem($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $editInventoryId = $this->nullIfEmpty($request->query('edit_inventory'));
        // Case-Inhalte sind physische Geräte — Bundles (Migration 107) ausblenden.
        $articles = $this->queries->ask(new ListArticles(onlyActive: true, excludeBundles: true))->items;

        // Anzeige-Label "Hersteller Artikelbezeichnung" je Artikel-ID
        // (Nutzerwunsch). Siehe OfferController::renderForm() für die
        // identische Begründung, warum $articles selbst unverändert bleibt.
        $manufacturersById = [];
        foreach ($this->queries->ask(new ListManufacturers()) as $manufacturer) {
            $manufacturersById[$manufacturer->id] = $manufacturer;
        }
        $articleDisplayNames = [];
        foreach ($articles as $article) {
            $articleDisplayNames[$article->id] = ArticleDisplayName::of($article, $manufacturersById[($article->manufacturerId ?? '')] ?? null);
        }

        echo View::page('cases/show', [
            'detail' => $detail,
            'editInventoryId' => $editInventoryId,
            'articles' => $articles,
            'articleDisplayNames' => $articleDisplayNames,
            'errors' => [],
            'old' => [],
        ], activeNav: 'cases', title: 'Case: ' . $detail->case->name);
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetCaseItem($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($detail->case, [], [], $detail->case->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateCaseItem(
            id: $id,
            name: $request->input('name', '') ?? '',
            manufacturerId: $request->input('manufacturer_id', '') ?? '',
            description: $request->input('description'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Case wurde aktualisiert.');
            Response::redirect('/cases/' . $id);
        } catch (ValidationException $e) {
            $detail = $this->queries->ask(new GetCaseItem($id));
            $this->renderForm($detail->case, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteCaseItem($id));
            Flash::success('Case wurde gelöscht.');
            Response::redirect('/cases');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function addInventoryNumber(Request $request): void
    {
        $caseId = $request->param('id');
        $acquisitionDate = $this->nullIfEmpty($request->input('acquisition_date'));

        try {
            $this->commands->dispatch(new AddCaseInventoryNumber($caseId, $acquisitionDate));
            Flash::success('Case-Inventurnummer wurde angelegt.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/cases/' . $caseId);
    }

    public function updateInventoryNumber(Request $request): void
    {
        $inventoryNumberId = $request->param('id');
        $caseId = $request->input('case_id', '') ?? '';
        $acquisitionDate = $this->nullIfEmpty($request->input('acquisition_date'));

        try {
            $this->commands->dispatch(new UpdateCaseInventoryNumber($inventoryNumberId, $acquisitionDate));
            Flash::success('Case-Inventurnummer wurde aktualisiert.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/cases/' . $caseId . '?edit_inventory=' . $inventoryNumberId);

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/cases/' . $caseId);
    }

    public function deleteInventoryNumber(Request $request): void
    {
        $inventoryNumberId = $request->param('id');
        $caseId = $request->input('case_id', '') ?? '';

        try {
            $this->commands->dispatch(new DeleteCaseInventoryNumber($inventoryNumberId));
            Flash::success('Case-Inventurnummer wurde gelöscht.');
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/cases/' . $caseId);
    }

    public function addContentItem(Request $request): void
    {
        $caseInventoryNumberId = $request->param('id');
        $caseId = $request->input('case_id', '') ?? '';
        $articleId = $request->input('article_id', '') ?? '';
        $quantity = $request->input('quantity', '') ?? '';

        try {
            $this->commands->dispatch(new AddCaseContentItem($caseInventoryNumberId, $articleId, $quantity));
            Flash::success('Artikel wurde zum Case hinzugefügt.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/cases/' . $caseId);
    }

    public function updateContentItem(Request $request): void
    {
        $contentItemId = $request->param('id');
        $caseId = $request->input('case_id', '') ?? '';
        $quantity = $request->input('quantity', '') ?? '';

        try {
            $this->commands->dispatch(new UpdateCaseContentItem($contentItemId, $quantity));
            Flash::success('Menge wurde aktualisiert.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/cases/' . $caseId);
    }

    public function deleteContentItem(Request $request): void
    {
        $contentItemId = $request->param('id');
        $caseId = $request->input('case_id', '') ?? '';

        try {
            $this->commands->dispatch(new DeleteCaseContentItem($contentItemId));
            Flash::success('Artikel wurde aus dem Case entfernt.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/cases/' . $caseId);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?CaseItem $case, array $errors, array $old, ?string $editingId = null): void
    {
        $manufacturers = $this->queries->ask(new ListManufacturers());

        echo View::page('cases/form', [
            'case' => $case,
            'manufacturers' => $manufacturers,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
        ], activeNav: 'cases', title: $case !== null ? 'Case bearbeiten' : 'Neuer Case');
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateCaseItem|UpdateCaseItem $command): array
    {
        return [
            'name' => $command->name,
            'manufacturer_id' => $command->manufacturerId,
            'description' => $command->description,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
