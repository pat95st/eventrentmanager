<?php

declare(strict_types=1);

namespace App\Application\Insurance\Estimation;

use DateTimeImmutable;

/** Angaben zu einem Gerät, aus denen die KI den heutigen Zeitwert schätzt. */
final class CurrentValueEstimationRequest
{
    public function __construct(
        public readonly ?string $category,
        public readonly ?string $manufacturer,
        public readonly string $model,
        public readonly ?string $description,
        public readonly ?DateTimeImmutable $acquisitionDate,
        public readonly ?string $purchasePrice,
        public readonly DateTimeImmutable $today,
    ) {
    }

    /** Schlüssel für gleichartige Exemplare (gleicher Artikel, gleiches Jahr, gleicher Preis) — eine Schätzung reicht dann für alle. */
    public function cacheKey(): string
    {
        return implode('|', [
            (string) $this->manufacturer,
            $this->model,
            $this->acquisitionDate?->format('Y') ?? '',
            (string) $this->purchasePrice,
        ]);
    }
}
