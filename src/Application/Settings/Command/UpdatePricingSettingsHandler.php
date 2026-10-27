<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PriceRoundingDirection;
use App\Domain\Settings\PriceRoundingStep;
use App\Domain\Settings\PricingSettings;
use App\Domain\Settings\PricingSettingsRepositoryInterface;

/**
 * Nur leichte Validierung: accessoryFlatRatePercent muss numerisch 0-100
 * sein (siehe UpdateInvoiceSettingsHandler::vatRatePercent).
 * priceRoundingStep/priceRoundingDirection kommen aus festen <select>-Feldern
 * (siehe settings/pricing.php) — tryFrom() statt from(), damit ein
 * unerwarteter Wert (manipuliertes Formular) einen sauberen
 * Validierungsfehler statt einen Fatal Error ergibt.
 */
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

        $roundingStep = PriceRoundingStep::tryFrom($command->priceRoundingStep);
        if ($roundingStep === null) {
            $errors['priceRoundingStep'] = 'Bitte einen gültigen Rundungsschritt auswählen.';
        }

        $roundingDirection = PriceRoundingDirection::tryFrom($command->priceRoundingDirection);
        if ($roundingDirection === null) {
            $errors['priceRoundingDirection'] = 'Bitte eine gültige Rundungsrichtung auswählen.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->settingsRepository->save(new PricingSettings(
            accessoryFlatRatePercent: number_format((float) $percentValue, 2, '.', ''),
            priceRoundingStep: $roundingStep,
            priceRoundingDirection: $roundingDirection,
        ));

        return null;
    }
}
