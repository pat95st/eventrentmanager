<?php

declare(strict_types=1);

namespace App\Application\DigitalStagebox\Query;

use App\Domain\DigitalStagebox\DigitalProtocol;

final class DigitalStageboxSettings
{
    /**
     * @param DigitalProtocol[] $protocols
     * @param array<string, int> $usage Protokoll-ID => Anzahl Artikel
     */
    public function __construct(
        public readonly array $protocols,
        public readonly array $usage,
    ) {
    }
}
