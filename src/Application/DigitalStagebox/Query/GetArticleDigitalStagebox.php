<?php

declare(strict_types=1);

namespace App\Application\DigitalStagebox\Query;

use App\Application\Query\QueryInterface;

final class GetArticleDigitalStagebox implements QueryInterface
{
    public function __construct(public readonly string $articleId)
    {
    }
}
