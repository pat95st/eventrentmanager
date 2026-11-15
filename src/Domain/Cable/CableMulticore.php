<?php

declare(strict_types=1);

namespace App\Domain\Cable;

/**
 * Additional details of a cable whose type is an analog multicore
 * (migration 157): number of sends and returns, whether it is wound on a
 * cable drum and whether it uses Harting connectors.
 */
final class CableMulticore
{
    public const MAX_CHANNELS = 999;

    public function __construct(
        public int $sends = 0,
        public int $returns = 0,
        public bool $onDrum = false,
        public bool $hasHarting = false,
    ) {
    }

    /** Short form "sends/returns", e.g. "24/8". */
    public function channelSummary(): string
    {
        return $this->sends . '/' . $this->returns;
    }

    /** E.g. "24 Sends / 8 Returns · Trommel · Harting". */
    public function summary(): string
    {
        $parts = [$this->sends . ' Sends / ' . $this->returns . ' Returns'];
        if ($this->onDrum) {
            $parts[] = 'Trommel';
        }
        if ($this->hasHarting) {
            $parts[] = 'Harting';
        }

        return implode(' · ', $parts);
    }
}
