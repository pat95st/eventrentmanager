<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\TrustedDeviceRepositoryInterface;
use App\Domain\Auth\UserSessionRepositoryInterface;

final class AdminRevokeAllSessionsHandler implements CommandHandlerInterface
{
    /** Beendet zusätzlich das Vertrauen aller Geräte (Migration 145), z. B. bei verlorenem Gerät. */
    public function __construct(
        private readonly UserSessionRepositoryInterface $sessions,
        private readonly ?TrustedDeviceRepositoryInterface $trustedDevices = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof AdminRevokeAllSessions);

        $this->sessions->deleteAllForUser($command->userId);
        $this->trustedDevices?->deleteAllForUser($command->userId);

        return null;
    }
}
