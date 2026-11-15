<?php

declare(strict_types=1);

namespace App\Domain\Cable;

/**
 * Leitungsangaben eines Artikels (Migration 125): Typ, Länge, zwei Steckverbinder.
 * Analog multicores (migration 156) additionally carry sends/returns, drum and Harting flags.
 */
final class ArticleCable
{
    public function __construct(
        public readonly string $articleId,
        public string $cableTypeId,
        public string $lengthM, // DECIMAL als String, z. B. "10.00"
        public string $connectorAId,
        public string $connectorBId,
        public ?CableMulticore $multicore = null,
    ) {
    }

    /** Länge für die Anzeige: "10 m", "2,5 m". */
    public function lengthLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->lengthM, 2, ',', ''), '0'), ',') . ' m';
    }
}
