<?php

declare(strict_types=1);

namespace App\Application\MixingConsole\Query;

use App\Application\Query\QueryInterface;

final class GetArticleMixingConsole implements QueryInterface
{
    public function __construct(public readonly string $articleId)
    {
    }
}
