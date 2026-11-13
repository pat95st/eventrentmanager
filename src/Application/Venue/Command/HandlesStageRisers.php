<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\Riser;
use App\Domain\Venue\RiserShape;
use App\Domain\Venue\Stage;
use App\Shared\MeterInput;

/**
 * Gemeinsame Bausteine der Riser-Handler: Bühne und Riser samt Zugehörigkeit
 * zum Veranstaltungsort auflösen sowie die Eingaben prüfen.
 *
 * Erwartet in der nutzenden Klasse die Eigenschaften $stages
 * (VenueStageRepositoryInterface) und $risers
 * (VenueStageRiserRepositoryInterface).
 */
trait HandlesStageRisers
{
    /**
     * Die Bühne muss zum Veranstaltungsort gehören und feste Maße haben —
     * ohne Maße (mobile Bühne aus Elementbestand) gibt es keine Fläche, auf
     * der sich etwas platzieren ließe.
     */
    private function stageForRisers(string $stageId, string $venueId): Stage
    {
        $stage = $this->stages->findById($stageId);
        if ($stage === null || $stage->venueId !== $venueId) {
            throw new NotFoundException("Bühne \"{$stageId}\" wurde nicht gefunden.");
        }
        if ($stage->widthM === null || $stage->depthM === null) {
            throw new ValidationException(['stage' => 'Riser lassen sich erst platzieren, wenn die Bühne feste Abmessungen hat.']);
        }

        return $stage;
    }

    private function riserOnStage(string $riserId, Stage $stage): Riser
    {
        $riser = $this->risers->findById($riserId);
        if ($riser === null || $riser->stageId !== $stage->id) {
            throw new NotFoundException("Riser \"{$riserId}\" wurde nicht gefunden.");
        }

        return $riser;
    }

    /**
     * @return array{0: array<string, string>, 1: array{
     *     name: string|null, shape: RiserShape, width: float, depth: float|null, height: float,
     *     centerX: float, centerY: float, rotation: int
     * }|null} Fehler und — nur wenn fehlerfrei — die bereinigten Werte
     */
    private function validateRiserInput(
        Stage $stage,
        string $name,
        string $shape,
        string $width,
        string $depth,
        string $height,
        string $centerX,
        string $centerY,
        string $rotation,
    ): array {
        $errors = [];

        $name = trim($name);
        if (mb_strlen($name) > 100) {
            $errors['name'] = 'Die Bezeichnung des Risers darf höchstens 100 Zeichen lang sein.';
        }

        $shapeEnum = RiserShape::tryFrom($shape);
        if ($shapeEnum === null) {
            $errors['shape'] = 'Bitte eine gültige Form für den Riser auswählen.';
        }

        $widthLabel = $shapeEnum !== null && $shapeEnum->widthIsDiameter() ? 'einen gültigen Durchmesser' : 'eine gültige Breite';
        $widthM = MeterInput::parse($width);
        if ($widthM === null || $widthM <= 0.0 || $widthM > Riser::MAX_DIMENSION_M) {
            $errors['width'] = "Bitte {$widthLabel} in Metern angeben (größer als 0, z. B. 2 oder 1,5).";
        }

        $depthM = null;
        if ($shapeEnum !== null && $shapeEnum->hasDepth()) {
            $depthM = MeterInput::parse($depth);
            if ($depthM === null || $depthM <= 0.0 || $depthM > Riser::MAX_DIMENSION_M) {
                $errors['depth'] = 'Bitte eine gültige Tiefe in Metern angeben (größer als 0, z. B. 2 oder 1,5).';
            }
        }

        $heightM = MeterInput::parse($height);
        if ($heightM === null || $heightM <= 0.0 || $heightM > Riser::MAX_HEIGHT_M) {
            $errors['height'] = 'Bitte eine gültige Riser-Höhe in Metern angeben (größer als 0, z. B. 0,4).';
        }

        [$positionErrors, $position] = $this->validateRiserPosition($stage, $centerX, $centerY);
        $errors += $positionErrors;

        $rotation = trim($rotation);
        $rotationDeg = 0;
        if ($rotation !== '') {
            if (preg_match('/^-?\d{1,3}$/', $rotation) !== 1 || abs((int) $rotation) > 360) {
                $errors['rotation'] = 'Bitte die Drehung in ganzen Grad angeben (0 bis 359).';
            } else {
                $rotationDeg = (((int) $rotation % 360) + 360) % 360;
            }
        }

        if ($errors !== [] || $shapeEnum === null || $widthM === null || $heightM === null || $position === null) {
            return [$errors, null];
        }

        return [[], [
            'name' => $name === '' ? null : $name,
            'shape' => $shapeEnum,
            'width' => $widthM,
            'depth' => $depthM,
            'height' => $heightM,
            'centerX' => $position['x'],
            'centerY' => $position['y'],
            'rotation' => $rotationDeg,
        ]];
    }

    /**
     * Der Mittelpunkt muss auf der Bühne liegen; leere Angaben bedeuten
     * Bühnenmitte. Der Riser selbst darf über die Kante hinausragen — das
     * kommt in der Praxis vor (z. B. Anbau an der Hinterkante) und ist im
     * Bühnenplan sichtbar.
     *
     * @return array{0: array<string, string>, 1: array{x: float, y: float}|null}
     */
    private function validateRiserPosition(Stage $stage, string $centerX, string $centerY): array
    {
        $errors = [];
        $stageWidth = (float) $stage->widthM;
        $stageDepth = (float) $stage->depthM;

        $x = trim($centerX) === '' ? round($stageWidth / 2, 2) : MeterInput::parse($centerX);
        if ($x === null || $x > $stageWidth) {
            $errors['centerX'] = 'Der Abstand des Riser-Mittelpunkts von der linken Bühnenkante muss zwischen 0 und der Bühnenbreite liegen.';
        }

        $y = trim($centerY) === '' ? round($stageDepth / 2, 2) : MeterInput::parse($centerY);
        if ($y === null || $y > $stageDepth) {
            $errors['centerY'] = 'Der Abstand des Riser-Mittelpunkts von der Bühnenhinterkante muss zwischen 0 und der Bühnentiefe liegen.';
        }

        if ($errors !== [] || $x === null || $y === null) {
            return [$errors, null];
        }

        return [[], ['x' => $x, 'y' => $y]];
    }
}
