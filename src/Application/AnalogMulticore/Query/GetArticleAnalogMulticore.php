<?php

declare(strict_types=1);

namespace App\Application\AnalogMulticore\Query;

use App\Application\Query\QueryInterface;

final class GetArticleAnalogMulticore implements QueryInterface
{
    public function __construct(public readonly string $articleId)
    {
    }
}
