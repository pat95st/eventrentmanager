<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Query;

use App\Application\ArticleSet\ArticleLabelResolver;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\ArticleSet\ArticleSetRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class GetArticleSetHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ArticleSetRepositoryInterface $sets,
        private readonly ArticleLabelResolver $labels,
    ) {
    }

    public function handle(QueryInterface $query): ArticleSetDetail
    {
        assert($query instanceof GetArticleSet);

        $set = $this->sets->findById($query->id);
        if ($set === null) {
            throw new NotFoundException("Set \"{$query->id}\" wurde nicht gefunden.");
        }

        $items = [];
        foreach ($this->sets->findItems($set->id) as $item) {
            $items[] = [
                'articleId' => $item->articleId,
                'label' => $this->labels->label($item->articleId),
                'quantity' => $item->quantity,
                'isBundle' => $this->labels->article($item->articleId)?->isBundle ?? false,
            ];
        }

        $upgrades = [];
        foreach ($this->sets->findUpgrades($set->id) as $upgrade) {
            $upgrades[] = [
                'id' => $upgrade->id,
                'name' => $upgrade->name,
                'price' => $upgrade->price,
                'items' => array_map(fn ($item) => [
                    'articleId' => $item->articleId,
                    'label' => $this->labels->label($item->articleId),
                    'quantity' => $item->quantity,
                ], $upgrade->items),
            ];
        }

        return new ArticleSetDetail($set, $items, $this->sets->findRules($set->id), $upgrades);
    }
}
