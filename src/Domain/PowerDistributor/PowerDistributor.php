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

    /** Lesbare Absicherung eines Abgangs, z. B. "hinter FI F1 (Typ A 30 mA) und LS F2 (C16)". */
    public function protectionOf(Outlet $outlet): string
    {
        $device = $this->device($outlet->deviceId);
        if ($device === null) {
            return 'direkt von der Zuleitung (ohne Schutzorgan)';
        }
        if ($device->type === ProtectiveDeviceType::Rcbo) {
            return 'hinter FI/LS ' . $device->label . ' (' . $device->specification() . ')';
        }
        if ($device->type === ProtectiveDeviceType::Rcd) {
            return 'nur hinter FI ' . $device->label . ' (' . $device->specification() . ')';
        }
        $upstream = $this->device($device->upstreamDeviceId);
        if ($upstream === null) {
            return 'nur hinter LS ' . $device->label . ' (' . $device->specification() . ')';
        }

        return 'hinter FI ' . $upstream->label . ' (' . $upstream->specification() . ') und LS ' . $device->label . ' (' . $device->specification() . ')';
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
