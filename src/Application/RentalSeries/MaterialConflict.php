<?php

declare(strict_types=1);

namespace App\Application\RentalSeries;

/** Article that is needed more often than inventory numbers are free on at least one day. */
final class MaterialConflict
{
    public function __construct(
        public readonly string $articleId,
        public readonly string $articleName,
        public readonly int $needed,
        public readonly int $plannedElsewhere, // maximum on a single day
        public readonly int $total,            // active inventory numbers
    ) {
    }

    public function available(): int
    {
        return max(0, $this->total - $this->plannedElsewhere);
    }

    public function describe(): string
    {
        return "{$this->articleName}: {$this->needed} benötigt, nur {$this->available()} von {$this->total} frei";
    }
}
