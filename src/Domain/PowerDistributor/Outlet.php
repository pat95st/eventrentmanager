<?php

declare(strict_types=1);

namespace App\Domain\PowerDistributor;

/** Abgang (eine oder mehrere gleiche Steckdosen) hinter einem Schutzorgan oder direkt an der Zuleitung. */
final class Outlet
{
    public function __construct(
        public readonly string $id,
        public ConnectorType $connectorType,
        public int $quantity,
        public ?string $label,
        public ?string $deviceId, // null = direkt von der Zuleitung
        public int $sortOrder = 0,
        // Verwendete Phase (L1/L2/L3) eines einphasigen Abgangs, wenn die
        // Absicherung dreiphasig ist (Migration 129); sonst null.
        public ?string $phase = null,
    ) {
    }
}
