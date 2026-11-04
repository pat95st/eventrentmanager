<?php

declare(strict_types=1);

namespace App\Application\Cable;

/** Leitungsangaben aus dem Artikelformular (Migration 125). */
final class CableInput
{
    public function __construct(
        public readonly ?string $cableTypeId,
        public readonly ?string $length,
        public readonly ?string $connectorAId,
        public readonly ?string $connectorBId,
    ) {
    }
}
