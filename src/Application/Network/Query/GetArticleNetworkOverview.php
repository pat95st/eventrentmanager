<?php

declare(strict_types=1);

namespace App\Application\Network\Query;

use App\Application\Query\QueryInterface;

final class GetArticleNetworkOverview implements QueryInterface
{
    public function __construct(public readonly string $articleId)
    {
    }
}
