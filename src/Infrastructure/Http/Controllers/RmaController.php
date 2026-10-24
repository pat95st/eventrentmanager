<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\GetInventoryNumberContext;
use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Rma\Command\AttachRmaDocument;
use App\Application\Rma\Command\AttachRmaPackagePhoto;
use App\Application\Rma\Command\CreateRma;
use App\Application\Rma\Command\DeleteRma;
use App\Application\Rma\Command\DeleteRmaDocument;
use App\Application\Rma\Command\DeleteRmaPackagePhoto;
use App\Application\Rma\Command\UpdateRma;
use App\Application\Receipt\Query\ListReceipts;
use App\Application\Rma\Query\GetRma;
use App\Application\Rma\Query\ListRmas;
use App\Application\Rma\Query\ListRmasForInventoryNumber;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rma\RmaStatus;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class RmaController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    /** Artikelübergreifende RMA-Übersicht, erreichbar über das Hauptmenü. */
    public function overview(Request $request): void
    {
        $statusParam = $request->query('status');
        $status = null;
        if ($statusParam !== null && $statusParam !== '') {
            $status = RmaStatus::tryFrom($statusParam);
        }
        $search = $request->query('q');

        $entries = $this->queries->ask(new ListRmas($status, $search));

        echo View::page('rma/overview', [
            'entries' => $entries,
            'selectedStatus' => $status,
            'search' => $search ?? '',
        ], activeNav: 'rma', title: 'RMA-Übersicht');
    }

    /** Übersicht aller RMA-Vorgänge einer Inventurnummer inkl. Erfassungsformular. */
    public function index(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');

        try {
            $context = $this->queries->ask(new GetInventoryNumberContext($inventoryNumberId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $rmas = $this->queries->ask(new ListRmasForInventoryNumber($inventoryNumberId));
        $receiptOptions = $this->queries->ask(new ListReceipts(page: 1, pageSize: 500))->items;

        echo View::page('rma/index', [
            'context' => $context,
            'rmas' => $rmas,
            'editingRma' => null,
            'errors' => [],
            'old' => [],
            'receiptOptions' => $receiptOptions,
        ], activeNav: 'rma', title: 'RMA: ' . $context->inventoryNumber->number);
    }

    public function edit(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');
        $rmaId = $request->param('id');

        try {
            $context = $this->queries->ask(new GetInventoryNumberContext($inventoryNumberId));
            $editingRma = $this->queries->ask(new GetRma($rmaId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $rmas = $this->queries->ask(new ListRmasForInventoryNumber($inventoryNumberId));
        $receiptOptions = $this->queries->ask(new ListReceipts(page: 1, pageSize: 500))->items;

        echo View::page('rma/index', [
            'context' => $context,
            'rmas' => $rmas,
            'editingRma' => $editingRma,
            'errors' => [],
            'old' => [],
            'receiptOptions' => $receiptOptions,
        ], activeNav: 'rma', title: 'RMA: ' . $context->inventoryNumber->number);
    }

    public function store(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');

        $command = new CreateRma(
            inventoryNumberId: $inventoryNumberId,
            reason: $request->input('reason'),
            serviceProvider: $request->input('service_provider'),
            rmaReference: $request->input('rma_reference'),
            sentAt: $this->nullIfEmpty($request->input('sent_at')),
            returnedAt: $this->nullIfEmpty($request->input('returned_at')),
            notes: $request->input('notes'),
            kind: $request->input('kind', 'extern') ?? 'extern',
            workedHours: $request->input('worked_hours'),
            receiptIds: $request->inputArray('receipt_ids'),
        );

        try {
            $rmaId = $this->commands->dispatch($command);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/inventurnummern/' . $inventoryNumberId . '/rma');

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        // Direkt beim Anlegen können schon Dokumente/Versandfotos mitgegeben werden.
        $this->attachUploadedDocuments($request, $rmaId, 'documents');
        $this->attachUploadedPackagePhotos($request, $rmaId, 'package_photos');

        Flash::success('RMA-Vorgang wurde angelegt.');
        Response::redirect('/inventurnummern/' . $inventoryNumberId . '/rma');
    }

    public function update(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');
        $rmaId = $request->param('id');

        $command = new UpdateRma(
            id: $rmaId,
            reason: $request->input('reason'),
            serviceProvider: $request->input('service_provider'),
            rmaReference: $request->input('rma_reference'),
            sentAt: $this->nullIfEmpty($request->input('sent_at')),
            returnedAt: $this->nullIfEmpty($request->input('returned_at')),
            notes: $request->input('notes'),
            kind: $request->input('kind', 'extern') ?? 'extern',
            workedHours: $request->input('worked_hours'),
            receiptIds: $request->inputArray('receipt_ids'),
        );

        try {
            $this->commands->dispatch($command);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/inventurnummern/' . $inventoryNumberId . '/rma/' . $rmaId . '/bearbeiten');

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->attachUploadedDocuments($request, $rmaId, 'documents');
        $this->attachUploadedPackagePhotos($request, $rmaId, 'package_photos');

        Flash::success('RMA-Vorgang wurde aktualisiert.');
        Response::redirect('/inventurnummern/' . $inventoryNumberId . '/rma');
    }

    public function destroy(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');
        $rmaId = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteRma($rmaId));
            Flash::success('RMA-Vorgang wurde gelöscht.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/inventurnummern/' . $inventoryNumberId . '/rma');
    }

    public function uploadDocument(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');
        $rmaId = $request->param('id');

        $this->attachUploadedDocuments($request, $rmaId, 'document');

        Response::redirect('/inventurnummern/' . $inventoryNumberId . '/rma');
    }

    public function downloadDocument(Request $request): void
    {
        $documentId = $request->param('documentId');

        try {
            $rmaWithDocuments = $this->queries->ask(new GetRma($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $document = null;
        foreach ($rmaWithDocuments->documents as $candidate) {
            if ($candidate->id === $documentId) {
                $document = $candidate;
            }
        }

        if ($document === null) {
            Response::notFound('Dokument wurde nicht gefunden.');

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('rma/' . $document->rmaId, $document->storedFilename);
        Response::file($absolutePath, $document->originalFilename, $document->mimeType);
    }

    public function deleteDocument(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');
        $documentId = $request->param('documentId');

        try {
            $this->commands->dispatch(new DeleteRmaDocument($documentId));
            Flash::success('Dokument wurde gelöscht.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/inventurnummern/' . $inventoryNumberId . '/rma');
    }

    public function uploadPackagePhoto(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');
        $rmaId = $request->param('id');

        $this->attachUploadedPackagePhotos($request, $rmaId, 'package_photo');

        Response::redirect('/inventurnummern/' . $inventoryNumberId . '/rma');
    }

    /** Liefert ein Versandfoto aus (Response::file() setzt "inline", daher zeigt der Browser es direkt an — siehe img-Vorschau in rma/index.php). */
    public function viewPackagePhoto(Request $request): void
    {
        $photoId = $request->param('photoId');

        try {
            $rmaWithDocuments = $this->queries->ask(new GetRma($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $photo = null;
        foreach ($rmaWithDocuments->packagePhotos as $candidate) {
            if ($candidate->id === $photoId) {
                $photo = $candidate;
            }
        }

        if ($photo === null) {
            Response::notFound('Foto wurde nicht gefunden.');

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('rma-versandfotos/' . $photo->rmaId, $photo->storedFilename);
        Response::file($absolutePath, $photo->originalFilename, $photo->mimeType);
    }

    public function deletePackagePhoto(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');
        $photoId = $request->param('photoId');

        try {
            $this->commands->dispatch(new DeleteRmaPackagePhoto($photoId));
            Flash::success('Foto wurde gelöscht.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/inventurnummern/' . $inventoryNumberId . '/rma');
    }

    private function attachUploadedDocuments(Request $request, string $rmaId, string $fieldName): void
    {
        $files = $request->fileList($fieldName);
        if ($files === []) {
            $single = $request->file($fieldName);
            if ($single !== null) {
                $files = [$single];
            }
        }

        foreach ($files as $file) {
            try {
                $this->commands->dispatch(new AttachRmaDocument($rmaId, $file));
            } catch (ValidationException $e) {
                Flash::error(implode(' ', $e->errors()));
            }
        }
    }

    private function attachUploadedPackagePhotos(Request $request, string $rmaId, string $fieldName): void
    {
        $files = $request->fileList($fieldName);
        if ($files === []) {
            $single = $request->file($fieldName);
            if ($single !== null) {
                $files = [$single];
            }
        }

        foreach ($files as $file) {
            try {
                $this->commands->dispatch(new AttachRmaPackagePhoto($rmaId, $file));
            } catch (ValidationException $e) {
                Flash::error(implode(' ', $e->errors()));
            }
        }
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
