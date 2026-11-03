<?php

declare(strict_types=1);

namespace App\Application\PowerDistributor\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\PowerDistributor\PowerDistributorRepositoryInterface;

final class RemovePowerDistributorHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PowerDistributorRepositoryInterface $distributors)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RemovePowerDistributor);
        $this->distributors->delete($command->articleId);

        return null;
    }
}
