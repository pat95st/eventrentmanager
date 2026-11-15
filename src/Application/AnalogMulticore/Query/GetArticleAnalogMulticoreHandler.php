<?php

declare(strict_types=1);

namespace App\Application\AnalogMulticore\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\AnalogMulticore\AnalogMulticoreRepositoryInterface;
use App\Domain\AnalogMulticore\ArticleAnalogMulticore;

final class GetArticleAnalogMulticoreHandler implements QueryHandlerInterface
{
    public function __construct(private readonly AnalogMulticoreRepositoryInterface $multicores)
    {
    }

    public function handle(QueryInterface $query): ?ArticleAnalogMulticore
    {
        assert($query instanceof GetArticleAnalogMulticore);

        return $this->multicores->findByArticle($query->articleId);
    }
}
