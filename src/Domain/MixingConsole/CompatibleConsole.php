<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/** Mixing console that lists a given stagebox as compatible (migration 154). */
final class CompatibleConsole
{
    public function __construct(
        public readonly string $articleId,
        public readonly string $name,
        public readonly ?string $manufacturerName,
        public readonly StageboxCompatibility $compatibility,
    ) {
    }

    public function displayName(): string
    {
        return $this->manufacturerName !== null && $this->manufacturerName !== '' ? $this->manufacturerName . ' ' . $this->name : $this->name;
    }
}
