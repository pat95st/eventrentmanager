<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Create an invitation link another installation can enter to befriend us. */
final class CreatePartnerInvitation implements CommandInterface
{
    public function __construct(
        public readonly ?string $note = null,
    ) {
    }
}
