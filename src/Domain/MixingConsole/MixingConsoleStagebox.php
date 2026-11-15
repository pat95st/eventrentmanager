<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/** Digital stagebox (article) compatible with a mixing console (migration 153). */
final class MixingConsoleStagebox
{
    public function __construct(
        public readonly string $stageboxArticleId,
        public readonly StageboxCompatibility $compatibility,
    ) {
    }
}
