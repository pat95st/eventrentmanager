<?php

declare(strict_types=1);

namespace App\Application\Cable\Query;

use App\Application\Query\QueryInterface;

final class GetArticleCable implements QueryInterface
{
    public function __construct(public readonly string $articleId)
    {
    }
}
