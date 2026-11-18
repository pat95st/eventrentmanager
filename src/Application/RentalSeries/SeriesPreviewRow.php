<?php

declare(strict_types=1);

namespace App\Application\RentalSeries;

use DateTimeImmutable;

/** One proposed occurrence in the preview table before a series is created. */
final class SeriesPreviewRow
{
    /**
     * @param string[] $warnings
     * @param MaterialConflict[] $conflicts
     */
    public function __construct(
        public readonly DateTimeImmutable $startDate,
        public readonly DateTimeImmutable $endDate,
        public readonly string $name,
        public readonly string $location,
        public readonly array $warnings,
        public readonly array $conflicts,
        public readonly bool $suggestSkip, // holiday or blocked period: unchecked by default
    ) {
    }
}
