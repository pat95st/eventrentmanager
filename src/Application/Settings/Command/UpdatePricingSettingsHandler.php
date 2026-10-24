<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PricingSettings;
use App\Domain\Settings\PricingSettingsRepositoryInterface;

/** Nur leichte Validierung: accessoryFlatRatePercent muss numerisch 0-100 sein (siehe UpdateInvoiceSettingsHandler::vatRatePercent). */
final class UpdatePricingSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PricingSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdatePricingSettings);

        $errors = [];

        $rawPercent = str_replace(',', '.', trim($command->accessoryFlatRatePercent));
        $percentValue = filter_var($rawPercent, FILTER_VALIDATE_FLOAT);
        if ($percentValue === false || $percentValue < 0 || $percentValue > 100) {
            $errors['accessoryFlatRatePercent'] = 'Bitte einen gültigen Prozentsatz zwischen 0 und 100 angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->settingsRepository->save(new PricingSettings(
            accessoryFlatRatePercent: number_format((float) $percentValue, 2, '.', ''),
        ));

        return null;
    }
}
