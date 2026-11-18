<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Application\Query\QueryInterface;

/** Before/after overview for CopyRentalData. */
final class PreviewRentalCopy implements QueryInterface
{
    /**
     * @param string[] $targetRentalIds
     * @param string[] $scopes
     */
    public function __construct(
        public readonly string $sourceRentalId,
        public readonly array $targetRentalIds,
        public readonly array $scopes,
    ) {
    }
}
