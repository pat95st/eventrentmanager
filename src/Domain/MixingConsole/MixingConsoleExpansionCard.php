<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/**
 * Expansion card that fits a mixing console's slots (migration 154), e.g.
 * "Dante card, 64 in / 64 out". Without a protocol the card is analog.
 */
final class MixingConsoleExpansionCard
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $protocolId = null,
        public readonly int $inputs = 0,
        public readonly int $outputs = 0,
        public readonly ?string $notes = null,
    ) {
    }

    public function channelSummary(): string
    {
        return $this->inputs . ' in / ' . $this->outputs . ' out';
    }
}
