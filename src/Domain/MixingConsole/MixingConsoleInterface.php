<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/** Built-in digital interface of a mixing console, e.g. 2 × AES50 (migration 153). */
final class MixingConsoleInterface
{
    public function __construct(
        public readonly string $protocolId,
        public readonly int $quantity = 1,
    ) {
    }
}
