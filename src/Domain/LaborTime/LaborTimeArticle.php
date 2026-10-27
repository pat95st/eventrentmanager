<?php

declare(strict_types=1);

namespace App\Domain\LaborTime;

use DateTimeImmutable;

final class LaborTimeArticle
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public string $hourlyRate, // Stundensatz, Dezimalzahl als String (analog Receipt::$amount)
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        // Tagessatz, optional ZUSÄTZLICH zum Stundensatz hinterlegbar (NULL =
        // kein Tagessatz definiert). Beim Hinzufügen einer Arbeitszeit-Position
        // zu einem Angebot/einer Rechnung kann dann je Position zwischen
        // Stunden- und Tagesabrechnung gewählt werden, siehe LaborTimeBillingUnit.
        public ?string $dailyRate = null,
    ) {
    }
}
