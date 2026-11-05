<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandInterface;

/** An einer Veranstaltung benötigte Gewerke. */
final class SetRentalTrades implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        /** @var string[] */
        public readonly array $tradeIds,
    ) {
    }
}
