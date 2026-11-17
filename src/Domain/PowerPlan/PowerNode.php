<?php

declare(strict_types=1);

namespace App\Domain\PowerPlan;

use App\Domain\PowerDistributor\PowerDistributor;

/**
 * One physical unit in the power plan of an event: a consumer with its
 * input power or a power distributor (whose own consumption is ignored).
 * The input power is the article's average power if one is recorded
 * (migration 173), otherwise its peak power.
 * Planned articles have a quantity, so each unit is addressed by article ID
 * and running number.
 */
final class PowerNode
{
    public function __construct(
        public readonly string $articleId,
        public readonly int $unitNo,
        public readonly string $label,
        public readonly ?int $inputPowerW,
        public readonly ?PowerDistributor $distributor = null,
        public readonly bool $inputPowerIsAverage = false,
    ) {
    }

    public static function keyOf(string $articleId, int $unitNo): string
    {
        return $articleId . '#' . $unitNo;
    }

    public function key(): string
    {
        return self::keyOf($this->articleId, $this->unitNo);
    }

    public function isDistributor(): bool
    {
        return $this->distributor !== null;
    }
}
