<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\GoogleBusinessSettingsRepositoryInterface;

final class UpdateGoogleBusinessClientCredentialsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly GoogleBusinessSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateGoogleBusinessClientCredentials);

        $clientId = trim((string) $command->clientId);
        $clientSecret = trim((string) $command->clientSecret);
        if ($clientId === '' || $clientSecret === '') {
            throw new ValidationException(['client' => 'Bitte Client-ID und Client-Secret aus der Google Cloud Console angeben.']);
        }

        $current = $this->repository->get();
        $current->clientId = $clientId;
        $current->clientSecret = $clientSecret;
        $this->repository->save($current);

        return null;
    }
}
