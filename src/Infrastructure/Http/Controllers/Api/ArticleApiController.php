<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Article\Query\ArticleDetail;
use App\Application\Article\Query\ArticleListItem;
use App\Application\Article\Query\GetArticle;
use App\Application\Article\Query\ListArticlesForDisplay;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Lese-only REST-API für Artikel (siehe ApiTokenGuard für die
 * Zugriffsprüfung, in bootstrap.php verdrahtet). Absichtlich rein lesend —
 * die Anfrage war "Artikel auslesen", kein Schreibzugriff von außen.
 *
 * Zentrale Anlaufstelle für die Datenbeschaffung ist bewusst dieselbe wie
 * beim HTML-Frontend (ArticleController): index() nutzt den Use Case
 * ListArticlesForDisplay, show()/image() nutzen GetArticle — exakt die
 * Use Cases, die auch ArticleController für die Artikelübersicht bzw.
 * -detailseite aufruft. Dieser Controller enthält daher keine eigene
 * Hersteller-/Kategorie-Auflösung mehr, sondern nur noch die
 * JSON-Formatierung der bereits vom Use Case gelieferten, aufgelösten Daten.
 */
final class ArticleApiController
{
    private const DEFAULT_PAGE_SIZE = 100;
    private const MAX_PAGE_SIZE = 500;

    public function __construct(
        private readonly QueryBus $queries,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function index(Request $request): void
    {
        $onlyActive = $request->query('active') === '1';
        $search = $request->query('q');

        $page = max(1, (int) ($request->query('page') ?? '1'));
        $pageSize = (int) ($request->query('page_size') ?? (string) self::DEFAULT_PAGE_SIZE);
        $pageSize = max(1, min(self::MAX_PAGE_SIZE, $pageSize));

        // Derselbe Use Case wie ArticleController::index() (siehe dort).
        $result = $this->queries->ask(new ListArticlesForDisplay(
            searchTerm: $search,
            page: $page,
            pageSize: $pageSize,
            onlyActive: $onlyActive,
        ));

        $items = array_map(
            fn (ArticleListItem $item) => $this->listItemToArray($item),
            $result->items
        );

        Response::json([
            'data' => $items,
            'meta' => [
                'page' => $result->page,
                'page_size' => $result->pageSize,
                'total' => $result->totalCount,
                'total_pages' => $result->totalPages(),
            ],
        ]);
    }

    public function show(Request $request): void
    {
        try {
            // Derselbe Use Case wie ArticleController::show()/edit() (siehe dort).
            $detail = $this->queries->ask(new GetArticle($request->param('id')));
        } catch (NotFoundException $e) {
            Response::json(['error' => $e->getMessage()], 404);

            return;
        }

        Response::json(['data' => $this->detailToArray($detail)]);
    }

    /** Liefert ein Artikelbild aus (Token bereits von ApiTokenGuard geprüft). */
    public function image(Request $request): void
    {
        $articleId = $request->param('id');
        $imageId = $request->param('imageId');

        try {
            $detail = $this->queries->ask(new GetArticle($articleId));
        } catch (NotFoundException $e) {
            Response::json(['error' => $e->getMessage()], 404);

            return;
        }

        $image = null;
        foreach ($detail->article->images as $candidate) {
            if ($candidate->id === $imageId) {
                $image = $candidate;
            }
        }

        if ($image === null) {
            Response::json(['error' => 'Image was not found.'], 404);

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('articles', $image->filePath);
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'application/octet-stream') : 'application/octet-stream';

        Response::file($absolutePath, basename($image->filePath), $mimeType);
    }

    /** @return array<string, mixed> */
    private function listItemToArray(ArticleListItem $item): array
    {
        return $this->articleToArray($item->article, $item->manufacturer?->name, $item->category?->name);
    }

    /** @return array<string, mixed> */
    private function articleToArray(\App\Domain\Article\Article $article, ?string $manufacturerName, ?string $categoryName): array
    {
        return [
            'id' => $article->id,
            'name' => $article->name,
            'manufacturer' => $manufacturerName,
            'category' => $categoryName,
            'description' => $article->description,
            'weight' => $article->weightValue !== null ? ['value' => $article->weightValue, 'unit' => $article->weightUnit] : null,
            'dimensions' => $article->dimensions,
            'requires_electrical_inspection' => $article->requiresElectricalInspection,
            'billable' => $article->isBillable,
            'active' => $article->isActive,
            'daily_rental_price' => $article->dailyRentalPrice,
            'images' => array_map(
                fn ($image) => ['id' => $image->id, 'url' => '/api/v1/articles/' . $article->id . '/images/' . $image->id],
                $article->images
            ),
            'created_at' => $article->createdAt->format(DATE_ATOM),
            'updated_at' => $article->updatedAt->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function detailToArray(ArticleDetail $detail): array
    {
        $data = $this->articleToArray($detail->article, $detail->manufacturer->name, $detail->category?->name);
        $data['inventory_numbers'] = array_map(
            fn ($entry) => [
                'number' => $entry->inventoryNumber->number,
                'acquisition_date' => $entry->inventoryNumber->acquisitionDate?->format('Y-m-d'),
            ],
            $detail->inventoryNumbers
        );

        return $data;
    }
}
