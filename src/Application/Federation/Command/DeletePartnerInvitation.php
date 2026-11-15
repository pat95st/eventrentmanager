<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Revoke an unused invitation link. */
final class DeletePartnerInvitation implements CommandInterface
{
    public function __construct(
        public readonly string $invitationId,
    ) {
    }
}
