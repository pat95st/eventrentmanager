<?php

declare(strict_types=1);

namespace App\Application\Search;

/** One hit of the global search. */
final class SearchResult
{
    public function __construct(
        public readonly SearchTarget $target,
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $subtitle = null,
    ) {
    }
}
