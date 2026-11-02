<?php

declare(strict_types=1);

namespace App\Domain\ArticleSet;

use DateTimeImmutable;

/**
 * Set (Nutzerwunsch, Migration 113): feste Zusammenstellung aus Artikeln
 * und/oder Bundles mit festem Preis für eine feste Anzahl Einsatztage.
 * Optional zeitlich eingeschränkt (ArticleSetRule) und um Upgrades
 * (ArticleSetUpgrade) erweiterbar.
 */
final class ArticleSet
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public ?string $description,
        public string $price, // Festpreis in Euro, als String (siehe Article::$dailyRentalPrice)
        public int $deploymentDays, // feste Anzahl Einsatztage, die der Preis abdeckt
        public bool $isActive,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
