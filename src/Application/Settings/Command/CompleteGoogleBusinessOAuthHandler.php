<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\GoogleBusinessSettingsRepositoryInterface;
use App\Infrastructure\Integration\Google\GoogleBusinessApiClient;
use DateTimeImmutable;

/**
 * Tauscht den von Google gelieferten Autorisierungscode gegen Access-/
 * Refresh-Token und ermittelt anschließend Konto- und Standort-ID, damit
 * die Anwendung ohne weiteres Zutun weiß, wohin sie Beiträge veröffentlichen
 * soll (siehe GoogleBusinessController::callback()).
 */
final class CompleteGoogleBusinessOAuthHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GoogleBusinessSettingsRepositoryInterface $repository,
        private readonly GoogleBusinessApiClient $client,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CompleteGoogleBusinessOAuth);

        $current = $this->repository->get();
        if ($current->clientId === null || $current->clientSecret === null) {
            throw new ValidationException(['oauth' => 'Bitte zuerst Client-ID und Client-Secret hinterlegen.']);
        }

        try {
            $tokens = $this->client->exchangeCode($current->clientId, $current->clientSecret, $command->redirectUri, $command->code);
        } catch (\Throwable $e) {
            throw new ValidationException(['oauth' => 'Verbindung zu Google fehlgeschlagen: ' . $e->getMessage()]);
        }

        $current->accessToken = $tokens['accessToken'];
        $current->refreshToken = $tokens['refreshToken'];
        $current->accessTokenExpiresAt = $tokens['expiresAt'];
        $current->connectedAt = new DateTimeImmutable();

        try {
            $accountId = $this->client->findFirstAccountId($tokens['accessToken']);
            $current->accountId = $accountId;
            $current->locationId = $accountId !== null ? $this->client->findFirstLocationId($tokens['accessToken'], $accountId) : null;
        } catch (\Throwable) {
            // Konto-/Standort-Ermittlung ist ein Komfort-Feature — schlägt sie fehl,
            // bleibt die Verbindung selbst trotzdem bestehen; die Felder bleiben null
            // und werden in den Einstellungen als "nicht ermittelt" angezeigt.
        }

        $this->repository->save($current);

        return null;
    }
}
