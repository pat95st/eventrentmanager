<?php

declare(strict_types=1);

namespace App\Application\Routing\Command;

use App\Application\Command\CommandInterface;

/** Settings page "Routenplanung": an empty key keeps the stored one unless $removeKey is set. */
final class SaveRoutingSettings implements CommandInterface
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $bufferMinutes,
        public readonly bool $removeKey = false,
    ) {
    }
}
