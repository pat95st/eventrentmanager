<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;

/**
 * Gemeinsame Validierung für Create-/UpdateVehicleHandler, damit die Regeln
 * nicht an zwei Stellen auseinanderlaufen können (siehe ValidatesSupplierInput
 * für das gleiche Muster bei den Lieferanten).
 *
 * Fachliche Regeln (siehe Vehicle-Klasse):
 * - Anhängekupplung/Anhängelast sind nur bei category = Auto relevant;
 *   bei category = Anhaenger werden beide stillschweigend auf null gesetzt.
 * - Ist eine Anhängekupplung vorhanden, muss die Anhängelast angegeben werden
 *   (sonst könnte die Überladungsprüfung des Anhängers nie durchgeführt werden).
 * - Das Leergewicht ist nur bei category = Anhaenger relevant; bei category =
 *   Auto wird es stillschweigend auf null gesetzt.
 * - Beifahrerplätze (0..Vehicle::MAX_PASSENGER_SEATS, ohne Fahrer) sind nur bei
 *   category = Auto relevant; bei category = Anhaenger werden sie
 *   stillschweigend auf null gesetzt.
 */
trait ValidatesVehicleInput
{
    /**
     * @return array{0: array<string, string>, 1: array{
     *     category: VehicleCategory|null, name: string, licensePlate: string|null,
     *     payloadKg: string|null, loadAreaLengthCm: string|null,
     *     loadAreaWidthCm: string|null, loadAreaHeightCm: string|null,
     *     loadAreaOpenTop: bool,
     *     towHitchAvailable: bool|null, towCapacityKg: string|null,
     *     emptyWeightKg: string|null, notes: string|null,
     *     acquisitionYear: int|null, purchasePrice: string|null, currentValue: string|null,
     *     heightCm: string|null, widthCm: string|null, lengthCm: string|null,
     *     grossWeightKg: string|null, passengerSeats: int|null
     * }}
     */
    private function validateVehicleInput(
        string $rawCategory,
        string $name,
        ?string $licensePlate,
        ?string $payloadKg,
        ?string $loadAreaLengthCm,
        ?string $loadAreaWidthCm,
        ?string $loadAreaHeightCm,
        bool $loadAreaOpenTop,
        bool $towHitchAvailable,
        ?string $towCapacityKg,
        ?string $emptyWeightKg,
        ?string $notes,
        ?string $acquisitionYear = null,
        ?string $purchasePrice = null,
        ?string $currentValue = null,
        ?string $heightCm = null,
        ?string $widthCm = null,
        ?string $lengthCm = null,
        ?string $grossWeightKg = null,
        ?string $passengerSeats = null,
    ): array {
        $errors = [];

        $category = VehicleCategory::tryFrom($rawCategory);
        if ($category === null) {
            $errors['category'] = 'Bitte eine gültige Kategorie (Auto/Anhänger) wählen.';
        }

        $name = trim($name);
        if ($name === '') {
            $errors['name'] = 'Bitte eine Bezeichnung angeben.';
        }

        $licensePlate = $licensePlate !== null ? trim($licensePlate) : null;
        if ($licensePlate === '') {
            $licensePlate = null;
        }

        $payloadKg = $this->parseOptionalDecimal($payloadKg, 'payloadKg', 'Bitte die Zuladung als Zahl (kg) angeben.', $errors);

        $loadAreaLengthCm = $this->parseOptionalDecimal($loadAreaLengthCm, 'loadAreaLengthCm', 'Bitte die Länge der Ladefläche als Zahl (cm) angeben.', $errors);
        $loadAreaWidthCm = $this->parseOptionalDecimal($loadAreaWidthCm, 'loadAreaWidthCm', 'Bitte die Breite der Ladefläche als Zahl (cm) angeben.', $errors);
        $loadAreaHeightCm = $this->parseOptionalDecimal($loadAreaHeightCm, 'loadAreaHeightCm', 'Bitte die Höhe der Ladefläche als Zahl (cm) angeben.', $errors);

        // Bei offener Ladefläche (kein Aufbau) gibt es keine Höhenbegrenzung
        // — die Höhe wird dann unabhängig von der Eingabe auf null
        // zurückgesetzt, analog zu Article::heightUnits bei is19Inch = false
        // (siehe ValidatesVehicleInput-Docblock oben).
        if ($loadAreaOpenTop) {
            $loadAreaHeightCm = null;
            unset($errors['loadAreaHeightCm']);
        }

        $towCapacityKg = $this->parseOptionalDecimal($towCapacityKg, 'towCapacityKg', 'Bitte die Anhängelast als Zahl (kg) angeben.', $errors);
        $emptyWeightKg = $this->parseOptionalDecimal($emptyWeightKg, 'emptyWeightKg', 'Bitte das Leergewicht als Zahl (kg) angeben.', $errors);

        // Felder sind je Kategorie nur in eine Richtung relevant (siehe
        // Docblock oben) — die jeweils nicht passenden Werte werden
        // unabhängig von der Eingabe auf null zurückgesetzt, statt einen
        // Fehler zu zeigen, damit ein Umschalten der Kategorie im Formular
        // nicht zu "Geister"-Werten der vorherigen Kategorie führt.
        if ($category === VehicleCategory::Auto) {
            $emptyWeightKg = null;
            if (!$towHitchAvailable) {
                $towCapacityKg = null;
            } elseif ($towCapacityKg === null && !isset($errors['towCapacityKg'])) {
                $errors['towCapacityKg'] = 'Bitte die Anhängelast angeben, wenn eine Anhängekupplung vorhanden ist.';
            }
        } else {
            $towHitchAvailable = false;
            $towCapacityKg = null;
        }

        // Passenger seats (migration 180): whole number 0..max, cars only.
        $seats = null;
        $passengerSeats = $passengerSeats !== null ? trim($passengerSeats) : '';
        if ($category !== VehicleCategory::Anhaenger && $passengerSeats !== '') {
            if (preg_match('/^\d+$/', $passengerSeats) !== 1 || (int) $passengerSeats > Vehicle::MAX_PASSENGER_SEATS) {
                $errors['passengerSeats'] = 'Die Anzahl der Beifahrerplätze muss zwischen 0 und ' . Vehicle::MAX_PASSENGER_SEATS . ' liegen.';
            } else {
                $seats = (int) $passengerSeats;
            }
        }

        $notes = $notes !== null ? trim($notes) : null;
        if ($notes === '') {
            $notes = null;
        }

        // Insurance list values (optional).
        $year = null;
        $acquisitionYear = $acquisitionYear !== null ? trim($acquisitionYear) : '';
        if ($acquisitionYear !== '') {
            if (preg_match('/^\d{4}$/', $acquisitionYear) !== 1 || (int) $acquisitionYear < 1900 || (int) $acquisitionYear > (int) date('Y') + 1) {
                $errors['acquisitionYear'] = 'Bitte das Anschaffungsjahr vierstellig angeben (z. B. 2021).';
            } else {
                $year = (int) $acquisitionYear;
            }
        }
        $purchasePrice = $this->parseOptionalAmount($purchasePrice, 'purchasePrice', 'Bitte den Anschaffungspreis als Betrag in Euro angeben.', $errors);
        $currentValue = $this->parseOptionalAmount($currentValue, 'currentValue', 'Bitte den Zeitwert als Betrag in Euro angeben.', $errors);

        // Dimensions for truck routing (migration 178)
        $heightCm = $this->parseOptionalDecimal($heightCm, 'heightCm', 'Bitte die Fahrzeughöhe als Zahl (cm) angeben.', $errors);
        $widthCm = $this->parseOptionalDecimal($widthCm, 'widthCm', 'Bitte die Fahrzeugbreite als Zahl (cm) angeben.', $errors);
        $lengthCm = $this->parseOptionalDecimal($lengthCm, 'lengthCm', 'Bitte die Fahrzeuglänge als Zahl (cm) angeben.', $errors);
        $grossWeightKg = $this->parseOptionalDecimal($grossWeightKg, 'grossWeightKg', 'Bitte das zulässige Gesamtgewicht als Zahl (kg) angeben.', $errors);

        return [$errors, [
            'category' => $category,
            'name' => $name,
            'licensePlate' => $licensePlate,
            'payloadKg' => $payloadKg,
            'loadAreaLengthCm' => $loadAreaLengthCm,
            'loadAreaWidthCm' => $loadAreaWidthCm,
            'loadAreaHeightCm' => $loadAreaHeightCm,
            'loadAreaOpenTop' => $loadAreaOpenTop,
            'towHitchAvailable' => $category === VehicleCategory::Auto ? $towHitchAvailable : null,
            'towCapacityKg' => $towCapacityKg,
            'emptyWeightKg' => $emptyWeightKg,
            'notes' => $notes,
            'acquisitionYear' => $year,
            'purchasePrice' => $purchasePrice,
            'currentValue' => $currentValue,
            'heightCm' => $heightCm,
            'widthCm' => $widthCm,
            'lengthCm' => $lengthCm,
            'grossWeightKg' => $grossWeightKg,
            'passengerSeats' => $category === VehicleCategory::Auto ? $seats : null,
        ]];
    }

    /**
     * Euro amount with optional thousands separators ("1.234,50"), stored as "1234.50".
     *
     * @param array<string, string> $errors
     */
    private function parseOptionalAmount(?string $value, string $field, string $message, array &$errors): ?string
    {
        $value = $value !== null ? trim(str_replace(['€', ' '], '', $value)) : null;
        if ($value === null || $value === '') {
            return null;
        }
        // German notation: "." is a thousands separator if a decimal comma
        // follows or if it only groups digits in threes ("38.500").
        if (str_contains($value, ',') || preg_match('/^\d{1,3}(\.\d{3})+$/', $value) === 1) {
            $value = str_replace('.', '', $value);
        }
        $normalized = $this->parseOptionalDecimal($value, $field, $message, $errors);
        if ($normalized === null) {
            return null;
        }
        if ((float) $normalized > 99999999.99) {
            $errors[$field] = $message;

            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    /** @param array<string, string> $errors */
    private function parseOptionalDecimal(?string $value, string $field, string $message, array &$errors): ?string
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            $errors[$field] = $message;

            return null;
        }

        return $normalized;
    }
}
