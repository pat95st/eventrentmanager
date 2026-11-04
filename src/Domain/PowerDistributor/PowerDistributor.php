<?php

declare(strict_types=1);

namespace App\Domain\PowerDistributor;

/**
 * Stromverteiler-Angaben eines Artikels (Migration 117): Zuleitung,
 * Schutzorgane und Abgänge. Die Zuordnung eines Abgangs ergibt sich aus
 * seinem Schutzorgan und dessen vorgeschaltetem RCD:
 * direkt | nur FI | nur LS | FI + LS | FI/LS-Kombination.
 */
final class PowerDistributor
{
    /**
     * @param ProtectiveDevice[] $devices
     * @param Outlet[] $outlets
     */
    public function __construct(
        public readonly string $articleId,
        public ConnectorType $inletType,
        public ?string $notes,
        public array $devices = [],
        public array $outlets = [],
    ) {
    }

    public function device(?string $id): ?ProtectiveDevice
    {
        foreach ($this->devices as $device) {
            if ($device->id === $id) {
                return $device;
            }
        }

        return null;
    }

    /** Lesbare Absicherung eines Abgangs, z. B. "hinter FI F1 (Typ A 30 mA) und LS F2 (C16)" plus Phase. */
    public function protectionOf(Outlet $outlet): string
    {
        $phase = $this->phaseOf($outlet);

        return $this->protectionText($outlet) . ($phase !== null ? ' · Phase ' . $phase : '');
    }

    private function protectionText(Outlet $outlet): string
    {
        $device = $this->device($outlet->deviceId);
        if ($device === null) {
            return 'direkt von der Zuleitung (ohne Schutzorgan)';
        }
        $name = $device->type->shortLabel() . ' ' . $device->label . ' (' . $device->specification() . ')';
        if ($device->type === ProtectiveDeviceType::Rcbo) {
            return 'hinter ' . $name;
        }
        if ($device->type === ProtectiveDeviceType::Rcd) {
            return 'nur hinter ' . $name;
        }
        $upstream = $this->device($device->upstreamDeviceId);
        if ($upstream === null) {
            return 'nur hinter ' . $name;
        }

        return 'hinter FI ' . $upstream->label . ' (' . $upstream->specification() . ') und ' . $name;
    }

    /**
     * Phase, an der ein einphasiger Abgang hängt: am Abgang gewählt (bei
     * dreiphasiger Absicherung) oder vom einphasigen Schutzorgan übernommen.
     */
    public function phaseOf(Outlet $outlet): ?string
    {
        if ($outlet->connectorType->isThreePhase()) {
            return null;
        }
        if ($outlet->phase !== null) {
            return $outlet->phase;
        }
        $device = $this->device($outlet->deviceId);

        return $device !== null && $device->isSinglePhase() ? $device->phase : null;
    }

    /** Hat der Verteiler Schmelzsicherungen (Neozed/Diazed)? Dann Ersatzsicherungen mitnehmen. */
    public function hasFuses(): bool
    {
        foreach ($this->devices as $device) {
            if ($device->type === ProtectiveDeviceType::Fuse) {
                return true;
            }
        }

        return false;
    }

    /**
     * Benötigte Sicherungseinsätze, z. B. ["Neozed 16 A" => 4, "Diazed 25 A" => 3].
     *
     * @return array<string, int>
     */
    public function fuseSummary(): array
    {
        $summary = [];
        foreach ($this->devices as $device) {
            $description = $device->fuseDescription();
            if ($description !== null) {
                $summary[$description] = ($summary[$description] ?? 0) + $device->fuseCount();
            }
        }

        return $summary;
    }

    /** Hat der Abgang Fehlerstromschutz? */
    public function hasResidualProtection(Outlet $outlet): bool
    {
        $device = $this->device($outlet->deviceId);
        if ($device === null) {
            return false;
        }

        return $device->type->hasResidualProtection() || $this->device($device->upstreamDeviceId)?->type === ProtectiveDeviceType::Rcd;
    }
}
