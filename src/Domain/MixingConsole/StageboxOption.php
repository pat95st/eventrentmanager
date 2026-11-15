<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/** Digital stagebox article as offered for compatibility selection (migration 154). */
final class StageboxOption
{
    public function __construct(
        public readonly string $articleId,
        public readonly string $name,
        public readonly ?string $manufacturerId,
        public readonly ?string $manufacturerName,
        public readonly string $protocolId,
    ) {
    }

    public function displayName(): string
    {
        return $this->manufacturerName !== null && $this->manufacturerName !== '' ? $this->manufacturerName . ' ' . $this->name : $this->name;
    }
}
