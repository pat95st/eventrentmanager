<?php

declare(strict_types=1);

namespace App\Application\Api\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Api\ApiTokenRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteApiTokenHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ApiTokenRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteApiToken);

        $token = $this->repository->findById($command->id);
        if ($token === null) {
            throw new NotFoundException("Token \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->repository->delete($command->id);

        return null;
    }
}
