<?php

declare(strict_types=1);

namespace App\Domain\PowerDistributor;

/**
 * Schutzorgan in einem Stromverteiler: RCD (FI), LS-Schalter oder
 * FI/LS-Kombination. Ein LS darf hinter einem RCD liegen ($upstreamDeviceId).
 */
final class ProtectiveDevice
{
    public const RCD_TYPES = ['AC', 'A', 'F', 'B', 'B+'];
    public const RESIDUAL_CURRENTS_MA = [10, 30, 100, 300, 500];
    public const CHARACTERISTICS = ['B', 'C', 'D', 'K', 'Z'];
    public const RATED_CURRENTS_A = [2, 4, 6, 10, 13, 16, 20, 25, 32, 40, 50, 63, 80, 100, 125];
    public const POLES = [1, 2, 3, 4];

    public function __construct(
        public readonly string $id,
        public string $label,
        public ProtectiveDeviceType $type,
        public ?int $poles,
        public ?string $rcdType,
        public ?int $residualCurrentMa,
        public ?int $ratedCurrentA,
        public ?string $characteristic,
        public ?string $upstreamDeviceId,
        public int $sortOrder = 0,
    ) {
    }

    /** Technische Kurzbeschreibung, z. B. "FI Typ A 30 mA, 40 A, 4-polig" oder "LS C16, 1-polig". */
    public function specification(): string
    {
        $parts = [];
        if ($this->type->hasResidualProtection()) {
            $parts[] = 'Typ ' . ($this->rcdType ?? '?') . ' ' . ($this->residualCurrentMa ?? '?') . ' mA';
        }
        if ($this->type->hasOvercurrentProtection()) {
            $parts[] = ($this->characteristic ?? '?') . ($this->ratedCurrentA ?? '?');
        } elseif ($this->ratedCurrentA !== null) {
            $parts[] = $this->ratedCurrentA . ' A';
        }
        if ($this->poles !== null) {
            $parts[] = $this->poles . '-polig';
        }

        return match ($this->type) {
            ProtectiveDeviceType::Rcd => 'FI ',
            ProtectiveDeviceType::Mcb => 'LS ',
            ProtectiveDeviceType::Rcbo => 'FI/LS ',
        } . implode(', ', $parts);
    }
}
