<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Query;

use App\Application\Query\QueryInterface;

/** Peak and average power of an article in watts (migrations 159 and 173) as an ArticlePower. */
final class GetArticleInputPower implements QueryInterface
{
    public function __construct(public readonly string $articleId)
    {
    }
}
