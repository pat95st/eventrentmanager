<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\IntegrationBaseSettings;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;

final class UpdateIntegrationBaseSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly IntegrationBaseSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateIntegrationBaseSettings);

        $baseUrl = trim((string) $command->baseUrl);
        if ($baseUrl !== '' && !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new ValidationException(['baseUrl' => 'Bitte eine gültige URL angeben, z. B. https://verleih.example.de']);
        }

        $this->repository->save(new IntegrationBaseSettings(baseUrl: $baseUrl !== '' ? $baseUrl : null));

        return null;
    }
}
