<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\Article\Command\AddArticleImage;
use App\Application\Article\Command\AddInventoryNumber;
use App\Application\Article\Command\CreateArticle;
use App\Application\Article\Command\DeleteArticle;
use App\Application\Article\Command\DeleteArticleImage;
use App\Application\Article\Command\DeleteInventoryNumber;
use App\Application\Article\Command\SetArticleActive;
use App\Application\Article\Command\UpdateArticle;
use App\Application\Article\Command\UpdateInventoryNumber;
use App\Application\Article\Query\ArticleDetail;
use App\Application\Article\Query\ArticleListItem;
use App\Application\Article\Query\GetArticle;
use App\Application\Article\Query\GetInventoryNumberContext;
use App\Application\Article\Query\InventoryNumberWithRmaInfo;
use App\Application\Article\Query\ListArticlesForDisplay;
use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Rental\Query\GetArticleAvailability;
use App\Domain\Article\Article;
use App\Domain\Article\ArticleBundleComponentRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\CommandFactory;
use App\Infrastructure\Http\Api\Normalizer;
use DateTimeImmutable;

/**
 * /api/v1/articles, /api/v1/inventory-numbers, /api/v1/availability —
 * scopes articles:read / articles:write.
 *
 * Uses the same use cases as the HTML frontend (ListArticlesForDisplay,
 * GetArticle, …). The article representation keeps the fields of the first
 * API version (manufacturer/category as names, weight object, billable,
 * active, image URLs) and adds the raw fields (manufacturer_id, prefix, …).
 */
final class ArticleApiController extends ApiController
{
    private const MAX_AVAILABILITY_DAYS = 366;
    private const MAX_AVAILABILITY_ARTICLES = 100;

    public function __construct(
        QueryBus $queries,
        CommandBus $commands,
        private readonly LocalFileStorage $fileStorage,
        private readonly ?ArticleBundleComponentRepositoryInterface $bundleComponents = null,
    ) {
        parent::__construct($queries, $commands);
    }

    public function index(ApiRequest $request): ApiResponse
    {
        $result = $this->ask(new ListArticlesForDisplay(
            manufacturerId: $request->query('manufacturer_id'),
            categoryId: $request->query('category_id'),
            onlyActive: $request->query('active') === '1',
        ));

        return $this->listResponse($request, array_map(
            fn (ArticleListItem $item) => $this->articleToArray($item->article, $item->manufacturer?->name, $item->category?->name) + ['stock_count' => $item->stockCount],
            $result->items
        ));
    }

    public function show(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data($this->detail($request->param('id')));
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $id = $this->run(CreateArticle::class, $request, ['powerDistributor' => null, 'cable' => null, 'digitalStagebox' => null, 'mixingConsole' => null]);

        return $this->created($this->detail((string) $id));
    }

    public function update(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $detail = $this->load($id);
        $current = CommandFactory::props($detail->article) + ['bundleComponents' => $this->bundleRows($id)];
        // Special sections (power distributor, cable, stagebox, mixing console) are
        // edited in the web UI only; null tells the handler to leave them unchanged.
        $this->run(UpdateArticle::class, $request, [
            'id' => $id,
            'powerDistributor' => null,
            'cable' => null,
            'digitalStagebox' => null,
            'mixingConsole' => null,
        ], $current);

        return ApiResponse::data($this->detail($id));
    }

    public function destroy(ApiRequest $request): ApiResponse
    {
        $this->dispatch(new DeleteArticle($request->param('id')));

        return ApiResponse::noContent();
    }

    public function activate(ApiRequest $request): ApiResponse
    {
        $this->dispatch(new SetArticleActive($request->param('id'), true));

        return ApiResponse::data($this->detail($request->param('id')));
    }

    public function deactivate(ApiRequest $request): ApiResponse
    {
        $this->dispatch(new SetArticleActive($request->param('id'), false));

        return ApiResponse::data($this->detail($request->param('id')));
    }

    /** Streams an article image (scope articles:read). */
    public function image(ApiRequest $request): ApiResponse
    {
        $detail = $this->load($request->param('id'));
        foreach ($detail->article->images as $image) {
            if ($image->id === $request->param('imageId')) {
                $path = $this->fileStorage->absolutePath('articles', $image->filePath);
                $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';

                return ApiResponse::file($path, basename($image->filePath), $mime);
            }
        }
        throw ApiException::notFound('Image was not found.');
    }

    /** multipart/form-data upload, field "file". */
    public function uploadImage(ApiRequest $request): ApiResponse
    {
        $file = $request->file('file') ?? throw ApiException::badRequest('Bitte eine Bilddatei im Feld "file" (multipart/form-data) mitschicken.');
        $imageId = $this->dispatch(new AddArticleImage($request->param('id'), $file));

        return $this->created(['id' => $imageId, 'url' => '/api/v1/articles/' . $request->param('id') . '/images/' . $imageId]);
    }

    public function deleteImage(ApiRequest $request): ApiResponse
    {
        $detail = $this->load($request->param('id'));
        $ids = array_map(static fn ($image) => $image->id, $detail->article->images);
        if (!in_array($request->param('imageId'), $ids, true)) {
            throw ApiException::notFound('Image was not found.');
        }
        $this->dispatch(new DeleteArticleImage($request->param('imageId')));

        return ApiResponse::noContent();
    }

    /** Availability of one article per day in [from, to]. */
    public function availability(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        [$from, $to] = $this->period($request);

        return ApiResponse::data($this->availabilityFor($request->param('id'), $from, $to));
    }

    /** Availability of several articles: ?article_ids=a,b,c&from=&to= */
    public function availabilityBulk(ApiRequest $request): ApiResponse
    {
        [$from, $to] = $this->period($request);
        $ids = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $request->query('article_ids', ''))))));
        if ($ids === []) {
            throw ApiException::badRequest('Parameter "article_ids" (kommagetrennte Artikel-IDs) fehlt.');
        }
        if (count($ids) > self::MAX_AVAILABILITY_ARTICLES) {
            throw ApiException::badRequest('Höchstens ' . self::MAX_AVAILABILITY_ARTICLES . ' Artikel pro Anfrage.');
        }
        $data = [];
        foreach ($ids as $id) {
            $this->load($id);
            $data[] = $this->availabilityFor($id, $from, $to);
        }

        return ApiResponse::json(['data' => $data]);
    }

    // --- Inventory numbers -------------------------------------------------

    public function inventoryNumbers(ApiRequest $request): ApiResponse
    {
        $detail = $this->load($request->param('id'));

        return $this->listResponse($request, array_map($this->inventoryNumberToArray(...), $detail->inventoryNumbers));
    }

    public function showInventoryNumber(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data($this->inventoryNumberDetail($request->param('id')));
    }

    public function storeInventoryNumber(ApiRequest $request): ApiResponse
    {
        $this->load($request->param('id'));
        $id = $this->run(AddInventoryNumber::class, $request, ['articleId' => $request->param('id'), 'networkInterfaces' => null]);

        return $this->created($this->inventoryNumberDetail((string) $id));
    }

    public function updateInventoryNumber(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $context = $this->ask(new GetInventoryNumberContext($id));
        $this->run(UpdateInventoryNumber::class, $request, [
            'id' => $id,
            'networkInterfaces' => null,
            'updateCurrentValue' => $request->has('current_value'),
        ], CommandFactory::props($context->inventoryNumber));

        return ApiResponse::data($this->inventoryNumberDetail($id));
    }

    public function destroyInventoryNumber(ApiRequest $request): ApiResponse
    {
        $this->ask(new GetInventoryNumberContext($request->param('id')));
        $this->dispatch(new DeleteInventoryNumber($request->param('id')));

        return ApiResponse::noContent();
    }

    // --- helpers -----------------------------------------------------------

    private function load(string $id): ArticleDetail
    {
        return $this->ask(new GetArticle($id));
    }

    /** @return array<string, mixed> */
    private function detail(string $id): array
    {
        $detail = $this->load($id);
        $data = $this->articleToArray($detail->article, $detail->manufacturer?->name, $detail->category?->name);
        $data['inventory_numbers'] = array_map($this->inventoryNumberToArray(...), $detail->inventoryNumbers);
        $data['bundle_components'] = $detail->article->isBundle ? $this->bundleRows($id) : [];

        return $data;
    }

    /** @return array<string, mixed> */
    private function articleToArray(Article $article, ?string $manufacturerName, ?string $categoryName): array
    {
        $raw = Normalizer::normalize($article, ['images', 'isBillable', 'isActive']);

        return array_merge($raw, [
            'manufacturer' => $manufacturerName,
            'category' => $categoryName,
            'weight' => $article->weightValue !== null ? ['value' => $article->weightValue, 'unit' => $article->weightUnit] : null,
            'billable' => $article->isBillable,
            'active' => $article->isActive,
            'images' => array_map(
                static fn ($image) => ['id' => $image->id, 'url' => '/api/v1/articles/' . $article->id . '/images/' . $image->id],
                $article->images
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function inventoryNumberToArray(InventoryNumberWithRmaInfo $entry): array
    {
        return Normalizer::normalize($entry->inventoryNumber) + [
            'open_rma_count' => $entry->openRmaCount,
            'total_rma_count' => $entry->totalRmaCount,
            'sold' => $entry->sale !== null,
            'firmware_outdated' => $entry->firmwareOutdated,
        ];
    }

    /** @return array<string, mixed> */
    private function inventoryNumberDetail(string $id): array
    {
        $context = $this->ask(new GetInventoryNumberContext($id));

        return Normalizer::normalize($context->inventoryNumber) + [
            'article' => ['id' => $context->article->id, 'name' => $context->article->name],
            'manufacturer' => $context->manufacturer->name,
            'firmware_outdated' => $context->firmwareOutdated,
        ];
    }

    /** @return list<array{article_id: string, quantity: string, alternative_ids: string}> */
    private function bundleRows(string $articleId): array
    {
        if ($this->bundleComponents === null) {
            return [];
        }

        return array_map(static fn ($component) => [
            'article_id' => $component->componentArticleId,
            'quantity' => (string) $component->quantity,
            'alternative_ids' => implode(',', $component->alternativeArticleIds),
        ], array_values($this->bundleComponents->findByBundle($articleId)));
    }

    /** @return array{DateTimeImmutable, DateTimeImmutable} */
    private function period(ApiRequest $request): array
    {
        $from = $this->dateQuery($request, 'from');
        $to = $this->dateQuery($request, 'to', $from);
        if ($to < $from) {
            throw ApiException::badRequest('"to" darf nicht vor "from" liegen.');
        }
        if ($from->diff($to)->days > self::MAX_AVAILABILITY_DAYS) {
            throw ApiException::badRequest('Der Zeitraum darf höchstens ' . self::MAX_AVAILABILITY_DAYS . ' Tage umfassen.');
        }

        return [$from, $to];
    }

    /** @return array<string, mixed> */
    private function availabilityFor(string $articleId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $availability = $this->ask(new GetArticleAvailability($articleId, $from->format('Y-m-d'), $to->format('Y-m-d')));
        $days = [];
        $min = $availability->totalInventory;
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $reserved = (int) ($availability->reservedByDay[$key] ?? 0);
            $available = max(0, $availability->totalInventory - $reserved);
            $min = min($min, $available);
            $days[] = ['date' => $key, 'reserved' => $reserved, 'available' => $available];
        }

        return [
            'article_id' => $articleId,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'total' => $availability->totalInventory,
            'available' => $min,
            'days' => $days,
        ];
    }
}
