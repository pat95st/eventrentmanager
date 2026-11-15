<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Query;

use App\Application\Query\QueryInterface;

/** Input power of an article in watts (migration 157); null = none/unknown. */
final class GetArticleInputPower implements QueryInterface
{
    public function __construct(public readonly string $articleId)
    {
    }
}
