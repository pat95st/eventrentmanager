<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\SecuritySettings;
use App\Domain\Settings\SecuritySettingsRepositoryInterface;

final class UpdateSecuritySettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly SecuritySettingsRepositoryInterface $settings)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateSecuritySettings);

        $raw = trim($command->sessionTimeoutMinutes);
        if ($raw === '' || preg_match('/^\d+$/', $raw) !== 1) {
            throw new ValidationException(['session_timeout_minutes' => 'Bitte eine ganze Zahl (Minuten) eingeben.']);
        }
        $minutes = (int) $raw;
        if ($minutes < SecuritySettings::MIN_SESSION_TIMEOUT_MINUTES || $minutes > SecuritySettings::MAX_SESSION_TIMEOUT_MINUTES) {
            throw new ValidationException(['session_timeout_minutes' => sprintf(
                'Erlaubt sind %d bis %d Minuten (30 Tage).',
                SecuritySettings::MIN_SESSION_TIMEOUT_MINUTES,
                SecuritySettings::MAX_SESSION_TIMEOUT_MINUTES,
            )]);
        }

        $settings = $this->settings->get();
        $settings->sessionTimeoutMinutes = $minutes;
        $this->settings->save($settings);

        return null;
    }
}
