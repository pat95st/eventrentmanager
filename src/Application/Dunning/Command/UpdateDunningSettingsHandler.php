<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Dunning\DunningSettings;
use App\Domain\Dunning\DunningSettingsRepositoryInterface;

final class UpdateDunningSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DunningSettingsRepositoryInterface $dunningSettings)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateDunningSettings);

        $this->dunningSettings->save(new DunningSettings(autoSendEnabled: $command->autoSendEnabled));

        return null;
    }
}
