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

        $markup = null;
        if ($command->travelCostMarkupPercent !== null) {
            $markup = $this->parseOptionalAmount(
                trim(str_replace('%', '', $command->travelCostMarkupPercent)) === '' ? '0' : str_replace('%', '', $command->travelCostMarkupPercent),
                'travelCostMarkupPercent',
                'Bitte den Aufschlag als Prozentwert zwischen 0 und 999 angeben (z. B. 10).',
                $errors,
            );
            if ($markup !== null && (float) $markup > 999.99) {
                $errors['travelCostMarkupPercent'] = 'Bitte den Aufschlag als Prozentwert zwischen 0 und 999 angeben (z. B. 10).';
            }
        }

        $note = $command->travelCostEstimateNote !== null ? trim($command->travelCostEstimateNote) : null;
        if ($note !== null && mb_strlen($note) > 1000) {
            $errors['travelCostEstimateNote'] = 'Der Hinweistext darf höchstens 1000 Zeichen lang sein.';
        }

        if ($errors !== [] || $auto === null || $trailer === null) {
            throw new ValidationException($errors);
        }

        $settings = $this->settings->get();
        $settings->defaultCostPerKmAuto = $auto;
        $settings->defaultCostPerKmTrailer = $trailer;
        if ($markup !== null) {
            $settings->travelCostMarkupPercent = $markup;
        }
        if ($note !== null) {
            $settings->travelCostEstimateNote = $note !== '' ? $note : null;
        }
        if ($command->eventTravelCostsDefault !== null) {
            $settings->eventTravelCostsDefault = $command->eventTravelCostsDefault;
        }
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
