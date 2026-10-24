<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Query;

use App\Domain\Article\Article;
use App\Domain\CaseContent\CaseContentItem;

final class CaseContentItemWithArticle
{
    public function __construct(
        public readonly CaseContentItem $item,
        public readonly Article $article,
    ) {
    }
}
