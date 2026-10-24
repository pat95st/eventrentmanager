<?php

declare(strict_types=1);

namespace App\Application\Warehouse\Command;

/**
 * Gemeinsame Validierung für Create-/UpdateWarehouseHandler, analog
 * App\Application\Venue\Command\ValidatesVenueInput.
 */
trait ValidatesWarehouseInput
{
    /**
     * @return array{0: array<string, string>, 1: array{
     *     name: string, street: string, postalCode: string, city: string,
     *     country: string|null, notes: string|null
     * }}
     */
    private function validateWarehouseInput(
        string $name,
        string $street,
        string $postalCode,
        string $city,
        ?string $country,
        ?string $notes,
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

        return [$errors, [
            'name' => $name,
            'street' => $street,
            'postalCode' => $postalCode,
            'city' => $city,
            'country' => $country,
            'notes' => $notes,
        ]];
    }
}
