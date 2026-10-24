<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

/**
 * Gemeinsame Validierung für Create-/UpdateVenueHandler, damit die Regeln
 * nicht an zwei Stellen auseinanderlaufen können.
 */
trait ValidatesVenueInput
{
    /**
     * @return array{0: array<string, string>, 1: array{
     *     name: string, street: string, postalCode: string, city: string,
     *     country: string|null, notes: string|null,
     *     caretakerName: string|null, caretakerPhone: string|null
     * }}
     */
    private function validateVenueInput(
        string $name,
        string $street,
        string $postalCode,
        string $city,
        ?string $country,
        ?string $notes,
        ?string $caretakerName = null,
        ?string $caretakerPhone = null,
    ): array {
        $errors = [];

        $name = trim($name);
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        }

        $street = trim($street);
        if ($street === '') {
            $errors['street'] = 'Bitte Straße und Hausnummer angeben.';
        }

        $postalCode = trim($postalCode);
        if ($postalCode === '') {
            $errors['postalCode'] = 'Bitte eine Postleitzahl angeben.';
        }

        $city = trim($city);
        if ($city === '') {
            $errors['city'] = 'Bitte einen Ort angeben.';
        }

        $country = $country !== null ? trim($country) : null;
        if ($country === '') {
            $country = null;
        }

        $notes = $notes !== null ? trim($notes) : null;
        if ($notes === '') {
            $notes = null;
        }

        // Hausmeister-Kontaktdaten sind ausdrücklich optional — reines
        // Trimmen/Normalisieren auf null, keine weitere Validierung.
        $caretakerName = $caretakerName !== null ? trim($caretakerName) : null;
        if ($caretakerName === '') {
            $caretakerName = null;
        }

        $caretakerPhone = $caretakerPhone !== null ? trim($caretakerPhone) : null;
        if ($caretakerPhone === '') {
            $caretakerPhone = null;
        }

        return [$errors, [
            'name' => $name,
            'street' => $street,
            'postalCode' => $postalCode,
            'city' => $city,
            'country' => $country,
            'notes' => $notes,
            'caretakerName' => $caretakerName,
            'caretakerPhone' => $caretakerPhone,
        ]];
    }
}
