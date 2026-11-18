<?php

declare(strict_types=1);

namespace App\Domain\RentalSeries;

use DateTimeImmutable;

/**
 * Recurring event (migration 176): rhythm plus naming of its occurrences.
 * The occurrences themselves are ordinary rentals (Rental::$seriesId).
 */
final class RentalSeries
{
    public function __construct(
        public readonly string $id,
        public RecurrenceRule $rule,
        public DateTimeImmutable $anchorDate,
        public ?string $nameTemplate,
        public ?string $sourceRentalId,
        public ?DateTimeImmutable $endedAt,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function isEnded(): bool
    {
        return $this->endedAt !== null;
    }

    /**
     * Name of an occurrence: the template with {datum}, {nr} and {jahr}
     * replaced, or $baseName when the series has no template.
     */
    public function nameFor(string $baseName, DateTimeImmutable $startDate, int $index): string
    {
        if ($this->nameTemplate === null || trim($this->nameTemplate) === '') {
            return $baseName;
        }

        return mb_substr(trim(strtr($this->nameTemplate, [
            '{datum}' => $startDate->format('d.m.Y'),
            '{nr}' => (string) $index,
            '{jahr}' => $startDate->format('Y'),
        ])), 0, 255);
    }
}
