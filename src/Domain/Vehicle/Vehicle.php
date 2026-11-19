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
        // Values for the insurance list of an event (see migration 170), all optional.
        public ?int $acquisitionYear = null,
        public ?string $purchasePrice = null, // purchase price in EUR
        public ?string $currentValue = null, // estimated current value in EUR
        // Migration 178: dimensions and permitted total weight for truck routing, all optional.
        public ?string $heightCm = null,
        public ?string $widthCm = null,
        public ?string $lengthCm = null,
        public ?string $grossWeightKg = null,
        // Migration 179: individual cost per km in EUR; null = default of the
        // category from VehicleSettings.
        public ?string $costPerKm = null,
    ) {
    }

    /** Cost per km that applies to this vehicle: its own value or the default of its category. */
    public function effectiveCostPerKm(VehicleSettings $settings): string
    {
        return $this->costPerKm ?? $settings->defaultCostPerKm($this->category);
    }

    public function usesDefaultCostPerKm(): bool
    {
        return $this->costPerKm === null;
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
