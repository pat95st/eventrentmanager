<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\GoogleBusinessSettingsRepositoryInterface;

final class DisconnectGoogleBusinessHandler implements CommandHandlerInterface
{
    public function __construct(private readonly GoogleBusinessSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DisconnectGoogleBusiness);

        $current = $this->repository->get();
        // Client-ID/Secret bleiben erhalten (das sind die App-Zugangsdaten aus der
        // Google Cloud Console, nicht die Nutzerverbindung) — nur die Verbindung
        // selbst wird getrennt.
        $current->accessToken = null;
        $current->refreshToken = null;
        $current->accessTokenExpiresAt = null;
        $current->accountId = null;
        $current->locationId = null;
        $current->connectedAt = null;

        $this->repository->save($current);

        return null;
    }
}
