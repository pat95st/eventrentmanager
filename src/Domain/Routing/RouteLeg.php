<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/** Leg between two consecutive route points. */
final class RouteLeg
{
    /** @param list<array{0: float, 1: float}> $geometry [lat, lng] pairs */
    public function __construct(
        public readonly int $from,
        public readonly int $to,
        public readonly float $distanceM,
        public readonly ?float $durationS, // null for straight lines
        public readonly array $geometry,
    ) {
    }
}
