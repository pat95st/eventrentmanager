<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Technician\TradeRepositoryInterface;

final class SetRentalTradesHandler implements CommandHandlerInterface
{
    public function __construct(private readonly TradeRepositoryInterface $trades)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SetRentalTrades);
        $known = array_map(static fn ($t) => $t->id, $this->trades->findAll());
        $this->trades->saveTradeIdsForRental($command->rentalId, array_values(array_intersect(array_map('strval', $command->tradeIds), $known)));

        return null;
    }
}
