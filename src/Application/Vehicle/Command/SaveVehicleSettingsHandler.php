<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;

final class SaveVehicleSettingsHandler implements CommandHandlerInterface
{
    use ValidatesVehicleInput;

    public function __construct(private readonly VehicleSettingsRepositoryInterface $settings)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveVehicleSettings);

        $errors = [];
        $auto = $this->parseRequiredCostPerKm(
            $command->defaultCostPerKmAuto,
            'defaultCostPerKmAuto',
            'Bitte die Kilometerkosten für Autos als Betrag in Euro angeben (z. B. 0,30).',
            $errors,
        );
        $trailer = $this->parseRequiredCostPerKm(
            $command->defaultCostPerKmTrailer,
            'defaultCostPerKmTrailer',
            'Bitte die Kilometerkosten für Anhänger als Betrag in Euro angeben (z. B. 0,15).',
            $errors,
        );

        if ($errors !== [] || $auto === null || $trailer === null) {
            throw new ValidationException($errors);
        }

        $settings = $this->settings->get();
        $settings->defaultCostPerKmAuto = $auto;
        $settings->defaultCostPerKmTrailer = $trailer;
        $this->settings->save($settings);

        return null;
    }

    /** @param array<string, string> $errors */
    private function parseRequiredCostPerKm(string $value, string $field, string $message, array &$errors): ?string
    {
        $amount = $this->parseOptionalAmount($value, $field, $message, $errors);
        if ($amount === null || (float) $amount > 9999.99) {
            $errors[$field] = $message;

            return null;
        }

        return $amount;
    }
}
