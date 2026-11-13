<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Domain\Venue\PowerConnection;
use App\Domain\Venue\Stage;
use App\Domain\Venue\StageType;

/**
 * Gemeinsame Validierung für Add-/UpdateVenueStageHandler. Längen werden in
 * Metern mit höchstens zwei Nachkommastellen (= Zentimeter) erwartet, Komma
 * oder Punkt als Dezimaltrennzeichen.
 */
trait ValidatesVenueStageInput
{
    /**
     * @param array<string, string> $powerDistances Stromanschluss-ID => Roh-Eingabe
     * @param PowerConnection[] $venueConnections Stromanschlüsse des Veranstaltungsorts der Bühne
     * @return array{0: array<string, string>, 1: array{
     *     name: string, width: float, depth: float, height: float, type: StageType,
     *     powerDistances: array<string, float>
     * }|null} Fehler und — nur wenn fehlerfrei — die bereinigten Werte
     */
    private function validateVenueStageInput(
        string $name,
        string $width,
        string $depth,
        string $height,
        string $type,
        array $powerDistances,
        array $venueConnections,
    ): array {
        $errors = [];

        $name = trim($name);
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen für die Bühne angeben.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'] = 'Der Name der Bühne darf höchstens 150 Zeichen lang sein.';
        }

        $widthM = self::parseMeters($width);
        if ($widthM === null || $widthM <= 0.0 || $widthM > Stage::MAX_DIMENSION_M) {
            $errors['width'] = 'Bitte eine gültige Breite in Metern angeben (größer als 0, z. B. 8 oder 8,5).';
        }

        $depthM = self::parseMeters($depth);
        if ($depthM === null || $depthM <= 0.0 || $depthM > Stage::MAX_DIMENSION_M) {
            $errors['depth'] = 'Bitte eine gültige Tiefe in Metern angeben (größer als 0, z. B. 6 oder 6,5).';
        }

        // 0 ist zulässig: ebenerdige Spielfläche ohne Podest.
        $heightM = self::parseMeters($height);
        if ($heightM === null || $heightM > Stage::MAX_DIMENSION_M) {
            $errors['height'] = 'Bitte eine gültige Bühnenhöhe in Metern angeben (z. B. 1 oder 0,8; 0 für ebenerdig).';
        }

        $typeEnum = StageType::tryFrom($type);
        if ($typeEnum === null) {
            $errors['type'] = 'Bitte auswählen, ob die Bühne fest installiert oder mobil ist.';
        }

        $knownConnectionIds = [];
        foreach ($venueConnections as $connection) {
            $knownConnectionIds[$connection->id] = true;
        }

        $distances = [];
        foreach ($powerDistances as $connectionId => $raw) {
            if (trim((string) $raw) === '') {
                continue;
            }
            if (!isset($knownConnectionIds[$connectionId])) {
                $errors['powerDistances'] = 'Eine Entfernung bezieht sich auf einen Stromanschluss, der nicht zu diesem Veranstaltungsort gehört.';

                continue;
            }
            $distance = self::parseMeters((string) $raw);
            if ($distance === null || $distance > Stage::MAX_DISTANCE_M) {
                $errors['powerDistances'] = 'Bitte die Entfernungen zu den Stromanschlüssen als Meterangabe eintragen (z. B. 15 oder 12,5).';

                continue;
            }
            $distances[(string) $connectionId] = $distance;
        }

        if ($errors !== [] || $widthM === null || $depthM === null || $heightM === null || $typeEnum === null) {
            return [$errors, null];
        }

        return [[], [
            'name' => $name,
            'width' => $widthM,
            'depth' => $depthM,
            'height' => $heightM,
            'type' => $typeEnum,
            'powerDistances' => $distances,
        ]];
    }

    /** "8,5", "8.5", "8,50 m" → 8.5; null bei leerer, negativer oder sonst ungültiger Eingabe. */
    private static function parseMeters(string $value): ?float
    {
        $value = str_replace([' ', "\u{00A0}"], '', trim($value));
        $value = (string) preg_replace('/m$/i', '', $value);
        $value = str_replace(',', '.', $value);

        if (preg_match('/^\d{1,4}(\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        return (float) $value;
    }
}
