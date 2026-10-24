<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Domain\Venue\CircuitBreakerRating;
use App\Domain\Venue\PowerConnectionType;
use App\Domain\Venue\RcdTripCurrent;

/**
 * Gemeinsame Validierung für Add-/UpdateVenuePowerConnectionHandler: die drei
 * Enum-Felder müssen einen der zulässigen Werte tragen (siehe
 * PowerConnectionType/CircuitBreakerRating/RcdTripCurrent), "Ort" ist
 * reiner, optionaler Freitext.
 */
trait ValidatesVenuePowerConnectionInput
{
    /**
     * @return array{0: array<string, string>, 1: array{
     *     type: PowerConnectionType|null, breakerRating: CircuitBreakerRating|null,
     *     rcdTripCurrent: RcdTripCurrent|null, location: string|null
     * }}
     */
    private function validateVenuePowerConnectionInput(
        string $type,
        string $breakerRating,
        string $rcdTripCurrent,
        ?string $location,
    ): array {
        $errors = [];

        $typeEnum = PowerConnectionType::tryFrom($type);
        if ($typeEnum === null) {
            $errors['type'] = 'Bitte eine gültige Anschlussart auswählen.';
        }

        $breakerEnum = CircuitBreakerRating::tryFrom($breakerRating);
        if ($breakerEnum === null) {
            $errors['breakerRating'] = 'Bitte eine gültige Leitungsschutzschalter-Größe auswählen.';
        }

        $rcdEnum = RcdTripCurrent::tryFrom($rcdTripCurrent);
        if ($rcdEnum === null) {
            $errors['rcdTripCurrent'] = 'Bitte einen gültigen RCD-Auslösestrom auswählen.';
        }

        $location = $location !== null ? trim($location) : null;
        if ($location === '') {
            $location = null;
        }

        return [$errors, [
            'type' => $typeEnum,
            'breakerRating' => $breakerEnum,
            'rcdTripCurrent' => $rcdEnum,
            'location' => $location,
        ]];
    }
}
