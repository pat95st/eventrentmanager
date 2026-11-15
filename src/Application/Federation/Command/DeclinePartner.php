<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Decline a partnership requested via one of our invitation links. */
final class DeclinePartner implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
    ) {
    }
}
