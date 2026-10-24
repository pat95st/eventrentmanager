<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleAccessory;
use App\Domain\Article\ArticlePreIssueCheck;
use App\Domain\Rental\RentalItem;

final class RentalItemWithArticle
{
    /**
     * @param ArticleAccessory[] $accessories
     * @param ArticlePreIssueCheck[] $preIssueChecks
     */
    public function __construct(
        public readonly RentalItem $item,
        public readonly Article $article,
        public readonly int $issuedCount,
        public readonly array $accessories,
        public readonly array $preIssueChecks = [],
    ) {
    }
}
