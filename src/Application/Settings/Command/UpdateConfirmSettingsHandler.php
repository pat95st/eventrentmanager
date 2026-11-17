<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\SecuritySettingsRepositoryInterface;

final class UpdateConfirmSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly SecuritySettingsRepositoryInterface $settings)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateConfirmSettings);

        $settings = $this->settings->get();
        if ($command->reset) {
            $settings->confirmOverrides = [];
        } else {
            $settings->confirmBypassEnabled = $command->bypassEnabled;
            $settings->setRequiredConfirmKeys($command->requiredKeys);
        }
        $this->settings->save($settings);

        return null;
    }
}
