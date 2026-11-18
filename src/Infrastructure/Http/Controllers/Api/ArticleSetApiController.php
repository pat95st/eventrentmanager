<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\ArticleSet\Command\CreateArticleSet;
use App\Application\ArticleSet\Command\DeleteArticleSet;
use App\Application\ArticleSet\Command\UpdateArticleSet;
use App\Application\ArticleSet\Query\ArticleSetDetail;
use App\Application\ArticleSet\Query\GetArticleSet;
use App\Application\ArticleSet\Query\ListArticleSets;
use App\Infrastructure\Http\Api\ApiController;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\CommandFactory;
use App\Infrastructure\Http\Api\Normalizer;

/**
 * /api/v1/article-sets — scopes articles:read / articles:write.
 *
 * items, rules and upgrades are returned in exactly the row format the
 * create/update commands accept, so a client can read, modify and send
 * them back (rules: from_month_day/to_month_day as "TT.MM.", dates as Y-m-d).
 */
final class ArticleSetApiController extends ApiController
{
    public function index(ApiRequest $request): ApiResponse
    {
        $sets = $this->ask(new ListArticleSets(onlyActive: $request->query('active') === '1'));

        return $this->listResponse($request, array_map($this->toArray(...), $sets));
    }

    public function show(ApiRequest $request): ApiResponse
    {
        return ApiResponse::data($this->toArray($this->ask(new GetArticleSet($request->param('id')))));
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $id = $this->run(CreateArticleSet::class, $request);

        return $this->created($this->toArray($this->ask(new GetArticleSet((string) $id))));
    }

    public function update(ApiRequest $request): ApiResponse
    {
        $id = $request->param('id');
        $detail = $this->ask(new GetArticleSet($id));
        $rows = $this->rows($detail);
        $current = CommandFactory::props($detail->set) + [
            'items' => $rows['items'],
            'rules' => $rows['rules'],
            'upgrades' => $rows['upgrades'],
        ];
        $this->run(UpdateArticleSet::class, $request, ['id' => $id], $current);

        return ApiResponse::data($this->toArray($this->ask(new GetArticleSet($id))));
    }

    public function destroy(ApiRequest $request): ApiResponse
    {
        $this->ask(new GetArticleSet($request->param('id')));
        $this->dispatch(new DeleteArticleSet($request->param('id')));

        return ApiResponse::noContent();
    }

    /** @return array<string, mixed> */
    private function toArray(ArticleSetDetail $detail): array
    {
        return Normalizer::normalize($detail->set) + $this->rows($detail);
    }

    /** @return array{items: list<array<string, string>>, rules: list<array<string, string>>, upgrades: list<array<string, mixed>>} */
    private function rows(ArticleSetDetail $detail): array
    {
        $monthDay = static fn (?string $md): string => $md !== null && preg_match('/^(\d{2})-(\d{2})$/', $md, $m) === 1 ? $m[2] . '.' . $m[1] . '.' : '';

        return [
            'items' => array_values(array_map(static fn (array $item) => [
                'article_id' => $item['articleId'],
                'label' => $item['label'],
                'quantity' => (string) $item['quantity'],
            ], $detail->items)),
            'rules' => array_values(array_map(static fn ($rule) => [
                'type' => $rule->type->value,
                'from_month_day' => $monthDay($rule->fromMonthDay),
                'to_month_day' => $monthDay($rule->toMonthDay),
                'from_date' => $rule->fromDate?->format('Y-m-d') ?? '',
                'to_date' => $rule->toDate?->format('Y-m-d') ?? '',
            ], $detail->rules)),
            'upgrades' => array_values(array_map(static fn (array $upgrade) => [
                'id' => $upgrade['id'],
                'name' => $upgrade['name'],
                'price' => $upgrade['price'],
                'items' => array_values(array_map(static fn (array $item) => [
                    'article_id' => $item['articleId'],
                    'label' => $item['label'],
                    'quantity' => (string) $item['quantity'],
                ], $upgrade['items'])),
            ], $detail->upgrades)),
        ];
    }
}
