<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\ListArticles;
use App\Application\Command\CommandBus;
use App\Application\Manufacturer\Query\ListManufacturers;
use App\Application\NoticeDocument\Command\DeleteNoticeDocument;
use App\Application\NoticeDocument\Command\UpdateNoticeDocumentAssignment;
use App\Application\NoticeDocument\Command\UploadNoticeDocument;
use App\Application\NoticeDocument\Query\GetNoticeDocumentDetail;
use App\Application\NoticeDocument\Query\ListNoticeDocuments;
use App\Application\Query\QueryBus;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Verwaltung generischer Hinweisdokumente (z. B. Bedienungsanleitungen,
 * Sicherheitshinweise), die bestimmten Artikeln zugeordnet und bei Bedarf
 * an Kunden verschickt werden können (siehe RentalController für den
 * Versand aus einer konkreten Veranstaltung heraus).
 */
final class NoticeDocumentController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function index(Request $request): void
    {
        $documents = $this->queries->ask(new ListNoticeDocuments());

        echo View::page('notice-documents/index', [
            'documents' => $documents,
        ], activeNav: 'notice_documents', title: t('notice_documents.title'));
    }

    public function create(Request $request): void
    {
        $articles = $this->queries->ask(new ListArticles(onlyActive: false))->items;

        echo View::page('notice-documents/form', [
            'detail' => null,
            'articles' => $articles,
            'articleDisplayNames' => $this->articleDisplayNames($articles),
            'errors' => [],
        ], activeNav: 'notice_documents', title: t('notice_documents.new'));
    }

    public function store(Request $request): void
    {
        $file = $request->file('document');

        try {
            $documentId = $this->commands->dispatch(new UploadNoticeDocument(
                name: $request->input('name', '') ?? '',
                uploadedFile: $file ?? ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
                articleIds: $request->inputArray('article_ids'),
            ));
            Flash::success(t('notice_documents.flash.created'));
            Response::redirect('/hinweisdokumente/' . $documentId);
        } catch (ValidationException $e) {
            $articles = $this->queries->ask(new ListArticles(onlyActive: false))->items;
            echo View::page('notice-documents/form', [
                'detail' => null,
                'articles' => $articles,
                'articleDisplayNames' => $this->articleDisplayNames($articles),
                'errors' => $e->errors(),
            ], activeNav: 'notice_documents', title: t('notice_documents.new'));
        }
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetNoticeDocumentDetail($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $articles = $this->queries->ask(new ListArticles(onlyActive: false))->items;

        echo View::page('notice-documents/form', [
            'detail' => $detail,
            'articles' => $articles,
            'articleDisplayNames' => $this->articleDisplayNames($articles),
            'errors' => [],
        ], activeNav: 'notice_documents', title: t('notice_documents.edit'));
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new UpdateNoticeDocumentAssignment(
                noticeDocumentId: $id,
                name: $request->input('name', '') ?? '',
                articleIds: $request->inputArray('article_ids'),
            ));
            Flash::success(t('notice_documents.flash.updated'));
            Response::redirect('/hinweisdokumente');
        } catch (ValidationException $e) {
            $detail = $this->queries->ask(new GetNoticeDocumentDetail($id));
            $articles = $this->queries->ask(new ListArticles(onlyActive: false))->items;
            echo View::page('notice-documents/form', [
                'detail' => $detail,
                'articles' => $articles,
                'articleDisplayNames' => $this->articleDisplayNames($articles),
                'errors' => $e->errors(),
            ], activeNav: 'notice_documents', title: t('notice_documents.edit'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    /**
     * Anzeige-Label "Hersteller Artikelbezeichnung" je Artikel-ID
     * (Nutzerwunsch). Siehe OfferController::renderForm() für die
     * identische Begründung, warum $articles selbst unverändert bleibt.
     *
     * @param \App\Domain\Article\Article[] $articles
     *
     * @return array<string, string>
     */
    private function articleDisplayNames(array $articles): array
    {
        $manufacturersById = [];
        foreach ($this->queries->ask(new ListManufacturers()) as $manufacturer) {
            $manufacturersById[$manufacturer->id] = $manufacturer;
        }

        $displayNames = [];
        foreach ($articles as $article) {
            $displayNames[$article->id] = ArticleDisplayName::of($article, $manufacturersById[$article->manufacturerId] ?? null);
        }

        return $displayNames;
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteNoticeDocument($id));
            Flash::success(t('notice_documents.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/hinweisdokumente');
    }

    public function download(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetNoticeDocumentDetail($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $document = $detail->document;
        $absolutePath = $this->fileStorage->absolutePath('hinweisdokumente', $document->storedFilename);
        Response::file($absolutePath, $document->originalFilename, $document->mimeType);
    }
}
