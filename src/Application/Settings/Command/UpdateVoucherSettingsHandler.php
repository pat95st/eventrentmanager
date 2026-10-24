<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\VoucherSettings;
use App\Domain\Settings\VoucherSettingsRepositoryInterface;

/** Nur leichte Validierung: defaultValidityDays muss eine ganze Zahl zwischen 1 und 3650 (10 Jahre) sein. */
final class UpdateVoucherSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly VoucherSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateVoucherSettings);

        $errors = [];

        $raw = trim($command->defaultValidityDays);
        $days = filter_var($raw, FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 3650) {
            $errors['defaultValidityDays'] = 'Bitte eine gültige Anzahl Tage zwischen 1 und 3650 angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->settingsRepository->save(new VoucherSettings(defaultValidityDays: $days));

        return null;
    }
}
