<?php

declare(strict_types=1);

namespace App\Domain\Depreciation;

use DateTimeImmutable;

/** Lesemodell: eine Inventarnummer mit allem, was die Abschreibung braucht. */
final class DepreciationAsset
{
    public function __construct(
        public readonly string $inventoryNumberId,
        public readonly string $number,
        public readonly string $articleId,
        public readonly string $articleName,
        public readonly ?string $manufacturerName,
        public readonly ?string $categoryId,
        public readonly ?string $categoryName,
        public readonly ?string $purchasePrice, // eigener Preis der Inventarnummer, sonst der des Artikels
        public readonly ?DateTimeImmutable $acquisitionDate,
        public readonly ?DateTimeImmutable $soldAt,
    ) {
    }

    /** Lässt sich für dieses Exemplar ein Abschreibungsplan berechnen? */
    public function isDepreciable(): bool
    {
        return $this->acquisitionDate !== null && $this->purchasePrice !== null && (float) $this->purchasePrice > 0;
    }

    public function label(): string
    {
        return trim(($this->manufacturerName ?? '') . ' ' . $this->articleName);
    }
}
