<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Query;

use App\Application\Query\QueryInterface;

/** Alle Sets (bzw. nur aktive) als ArticleSetDetail. */
final class ListArticleSets implements QueryInterface
{
    public function __construct(public readonly bool $onlyActive = false)
    {
    }
}
