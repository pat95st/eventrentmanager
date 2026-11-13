<?php

declare(strict_types=1);

namespace App\Domain\Venue;

use DateTimeImmutable;

/**
 * Ein Riser (Podest auf der Bühne, z. B. Drum-Riser), frei auf einer Bühne
 * platziert. Alle Längen in Metern.
 *
 * Die Position ist der MITTELPUNKT des Risers, gemessen von der linken
 * Bühnenkante ($centerXM) und von der Bühnenhinterkante ($centerYM) — nur der
 * Mittelpunkt bleibt bei einer Drehung an Ort und Stelle. Die Drehung zählt
 * im Uhrzeigersinn in der Draufsicht (Publikum unten).
 */
final class Riser
{
    /** Obergrenze für Breite/Tiefe (DECIMAL(5,2), praktisch: nie größer als eine Bühne). */
    public const MAX_DIMENSION_M = 99.99;

    /** Obergrenze für die Riser-Höhe. */
    public const MAX_HEIGHT_M = 9.99;

    /**
     * @param float $widthM Breite; bei Kreis und Halbkreis der Durchmesser
     * @param float|null $depthM Tiefe; nur bei Formen mit RiserShape::hasDepth()
     */
    public function __construct(
        public readonly string $id,
        public readonly string $stageId,
        public ?string $name,
        public RiserShape $shape,
        public float $widthM,
        public ?float $depthM,
        public float $heightM,
        public float $centerXM,
        public float $centerYM,
        public int $rotationDeg,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /** Tiefe der Grundfläche vor der Drehung — auch für Formen ohne eigene Tiefenangabe. */
    public function footprintDepthM(): float
    {
        return match ($this->shape) {
            RiserShape::Circle => $this->widthM,
            RiserShape::Semicircle => $this->widthM / 2,
            RiserShape::Rectangle, RiserShape::Triangle => $this->depthM ?? $this->widthM,
        };
    }

    public function areaSquareMeters(): float
    {
        $area = match ($this->shape) {
            RiserShape::Rectangle => $this->widthM * $this->footprintDepthM(),
            RiserShape::Triangle => $this->widthM * $this->footprintDepthM() / 2,
            RiserShape::Circle => M_PI * ($this->widthM / 2) ** 2,
            RiserShape::Semicircle => M_PI * ($this->widthM / 2) ** 2 / 2,
        };

        return round($area, 2);
    }
}
