<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Enter a partner's invitation link and send our confirmation and settings. */
final class AcceptPartnerInvitation implements CommandInterface
{
    public function __construct(
        public readonly string $link,
        public readonly bool $autoAcceptRequests,
        public readonly bool $allowTechnicianRequests,
        public readonly bool $symmetricDiscount,
        public readonly string $discount,
        public readonly string $discountGranted,
        public readonly string $discountReceived,
    ) {
    }
}
