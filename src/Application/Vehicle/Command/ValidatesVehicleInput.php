<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

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
     *     emptyWeightKg: string|null, notes: string|null
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

        $notes = $notes !== null ? trim($notes) : null;
        if ($notes === '') {
            $notes = null;
        }

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
        ]];
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
