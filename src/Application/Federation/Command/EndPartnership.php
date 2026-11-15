<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** End an active partnership (both sides lose access). */
final class EndPartnership implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
    ) {
    }
}
