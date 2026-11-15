<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/** Group of DSP channels of a digital mixing console, e.g. 16 × "Mix Bus" mono (migration 153). */
final class MixingConsoleChannel
{
    public function __construct(
        public readonly MixingConsoleChannelKind $kind,
        public readonly int $quantity,
        public readonly bool $isStereo = false,
        public readonly ?string $manufacturerLabel = null,
    ) {
    }

    /** Manufacturer designation if given, otherwise the generic kind label. */
    public function displayName(): string
    {
        return $this->manufacturerLabel ?? $this->kind->label();
    }
}
