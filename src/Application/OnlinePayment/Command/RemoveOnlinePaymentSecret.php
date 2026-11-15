<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Command;

use App\Application\Command\CommandInterface;
use App\Domain\OnlinePayment\OnlinePaymentProvider;

/** Deletes the stored secret of a provider and disables it. */
final class RemoveOnlinePaymentSecret implements CommandInterface
{
    public function __construct(public readonly OnlinePaymentProvider $provider)
    {
    }
}
