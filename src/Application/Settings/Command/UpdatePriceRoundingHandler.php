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

final class UpdatePriceRoundingHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PricingSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdatePriceRounding);

        $errors = [];
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

        $current = $this->settingsRepository->get();
        $this->settingsRepository->save(new PricingSettings(
            accessoryFlatRatePercent: $current->accessoryFlatRatePercent,
            priceRoundingStep: $roundingStep,
            priceRoundingDirection: $roundingDirection,
        ));

        return null;
    }
}
