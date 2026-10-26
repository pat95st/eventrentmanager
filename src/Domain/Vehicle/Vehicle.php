<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use DateTimeImmutable;

/**
 * Ein Fahrzeug des Fuhrparks (Auto oder Anhänger), siehe VehicleCategory.
 * Zuladung und Ladeflächenmaße werden bei beiden Kategorien gepflegt.
 * towHitchAvailable/towCapacityKg sind nur bei category = Auto relevant,
 * emptyWeightKg nur bei category = Anhaenger — die Anwendung erzwingt das
 * (siehe ValidatesVehicleInput), nicht die Domain-Klasse selbst (analog zu
 * Article::is19Inch/heightUnits).
 *
 * loadAreaHeightCm ist NULL, wenn loadAreaOpenTop = true (offene
 * Ladefläche/Pritsche ohne Aufbau, keine Höhenbegrenzung) — die Anwendung
 * erzwingt auch das wieder analog zu Article::heightUnits bei is19Inch.
 *
 * Gewichte/Maße werden als String geführt (siehe Article::$weightValue),
 * um Rundungsfehler bei DECIMAL zu vermeiden.
 */
final class Vehicle
{
    public function __construct(
        public readonly string $id,
        public VehicleCategory $category,
        public string $name,
        public ?string $licensePlate,
        public ?string $payloadKg, // Zuladung in kg
        public ?string $loadAreaLengthCm, // Länge der Ladefläche in cm
        public ?string $loadAreaWidthCm, // Breite der Ladefläche in cm
        public ?string $loadAreaHeightCm, // Höhe der Ladefläche in cm, null wenn loadAreaOpenTop
        public bool $loadAreaOpenTop, // offene Ladefläche (kein Aufbau) => keine Höhenbegrenzung
        public ?bool $towHitchAvailable, // nur bei category = Auto relevant
        public ?string $towCapacityKg, // Anhängelast in kg, nur wenn towHitchAvailable
        public ?string $emptyWeightKg, // Leergewicht in kg, nur bei category = Anhaenger
        public ?string $notes,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function isAuto(): bool
    {
        return $this->category === VehicleCategory::Auto;
    }

    public function isAnhaenger(): bool
    {
        return $this->category === VehicleCategory::Anhaenger;
    }
}
