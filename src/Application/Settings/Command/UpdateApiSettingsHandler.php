<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\ApiSettings;
use App\Domain\Settings\ApiSettingsRepositoryInterface;

final class UpdateApiSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ApiSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateApiSettings);

        $this->repository->save(new ApiSettings(enabled: $command->enabled));

        return null;
    }
}
