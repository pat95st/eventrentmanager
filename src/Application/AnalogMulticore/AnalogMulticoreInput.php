<?php

declare(strict_types=1);

namespace App\Application\AnalogMulticore;

/** Analog multicore fields from the article form (migration 156); counts as raw strings. */
final class AnalogMulticoreInput
{
    public function __construct(
        public readonly ?string $sends,
        public readonly ?string $returns,
        public readonly bool $onDrum = false,
        public readonly bool $hasHarting = false,
    ) {
    }
}
