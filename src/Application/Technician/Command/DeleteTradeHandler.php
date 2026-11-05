<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Technician\TradeRepositoryInterface;

final class DeleteTradeHandler implements CommandHandlerInterface
{
    public function __construct(private readonly TradeRepositoryInterface $trades)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteTrade);
        $this->trades->delete($command->id);

        return null;
    }
}
