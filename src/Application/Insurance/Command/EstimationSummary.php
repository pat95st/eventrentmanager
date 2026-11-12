<?php

declare(strict_types=1);

namespace App\Application\Insurance\Command;

final class EstimationSummary
{
    /** @param list<string> $errors */
    public function __construct(
        public readonly int $estimated,
        public readonly int $failed,
        public readonly int $remaining, // wegen des Anfragelimits übersprungen
        public readonly array $errors = [],
    ) {
    }
}
