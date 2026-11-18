<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Domain\Rental\Rental;
use App\Domain\RentalSeries\CopyScope;

final class RentalCopyPreview
{
    /**
     * @param CopyScope[] $scopes
     * @param list<array{rental: Rental, started: bool, changes: list<array{area: string, before: string, after: string}>}> $targets
     */
    public function __construct(
        public readonly Rental $source,
        public readonly array $scopes,
        public readonly array $targets,
    ) {
    }
}
