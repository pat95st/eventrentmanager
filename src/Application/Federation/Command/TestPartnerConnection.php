<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Check that the partner installation is reachable and accepts our token. */
final class TestPartnerConnection implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
    ) {
    }
}
