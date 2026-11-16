<?php

declare(strict_types=1);

namespace App\Domain\Display;

/**
 * Global display settings (migration 167, single row): how often clients
 * poll and when a countdown turns yellow (warn) or red (critical).
 */
final class DisplaySettings
{
    public function __construct(
        public int $pollIntervalSeconds = 3,
        public int $warnSeconds = 120,
        public int $criticalSeconds = 30,
    ) {
    }
}
