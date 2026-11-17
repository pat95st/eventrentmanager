<?php

declare(strict_types=1);

namespace App\Domain\PowerPlan;

/**
 * Power consumption of an article in watts (migrations 159 and 173): the
 * peak power per type plate and optionally the average power. When an
 * average is recorded it is the stated value; the peak power is only stated
 * (and planned with) when no average exists.
 */
final class ArticlePower
{
    public function __construct(
        public readonly ?int $peakW,
        public readonly ?int $averageW,
    ) {
    }

    /** The value to state and to plan with: the average if recorded, otherwise the peak. */
    public function statedW(): ?int
    {
        return $this->averageW ?? $this->peakW;
    }

    public function isAverage(): bool
    {
        return $this->averageW !== null;
    }
}
