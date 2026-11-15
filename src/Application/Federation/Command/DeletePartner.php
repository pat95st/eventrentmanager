<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Delete an ended, declined or unconfirmed partnership including its requests. */
final class DeletePartner implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
    ) {
    }
}
