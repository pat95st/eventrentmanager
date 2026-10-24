<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryInterface;

final class GetArticleAvailability implements QueryInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly string $from, // 'Y-m-d'
        public readonly string $to,   // 'Y-m-d'
    ) {
    }
}
