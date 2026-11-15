<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Propose new discounts; the partner has to accept them. */
final class ProposePartnerDiscount implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
        public readonly bool $symmetricDiscount,
        public readonly string $discount,
        public readonly string $discountGranted,
        public readonly string $discountReceived,
    ) {
    }
}
