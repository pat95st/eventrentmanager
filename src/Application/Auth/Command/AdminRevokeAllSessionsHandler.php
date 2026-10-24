<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\UserSessionRepositoryInterface;

final class AdminRevokeAllSessionsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserSessionRepositoryInterface $sessions)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof AdminRevokeAllSessions);

        $this->sessions->deleteAllForUser($command->userId);

        return null;
    }
}
