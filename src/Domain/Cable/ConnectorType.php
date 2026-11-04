<?php

declare(strict_types=1);

namespace App\Domain\Cable;

/** Steckverbindertyp, z. B. „CEE 32 A Stecker rot“ (Migration 125). */
final class ConnectorType
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public int $sortOrder = 0,
    ) {
    }
}
