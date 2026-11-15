<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Confirm a partnership requested via one of our invitation links. */
final class ConfirmPartner implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
        public readonly bool $autoAcceptRequests,
        public readonly bool $allowTechnicianRequests,
        public readonly bool $symmetricDiscount,
        public readonly string $discount,
        public readonly string $discountGranted,
        public readonly string $discountReceived,
    ) {
    }
}
