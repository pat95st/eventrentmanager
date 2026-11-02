<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Query;

use App\Application\Query\QueryInterface;

final class GetArticleSet implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
