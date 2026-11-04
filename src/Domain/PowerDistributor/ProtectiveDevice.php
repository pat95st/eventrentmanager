<?php

declare(strict_types=1);

namespace App\Domain\PowerDistributor;

/**
 * Schutzorgan in einem Stromverteiler: RCD (FI), LS-Schalter,
 * FI/LS-Kombination oder Schmelzsicherung (Neozed/Diazed, Migration 129).
 * LS und Sicherungen dürfen hinter einem RCD liegen ($upstreamDeviceId).
 *
 * $threeSingle: drei einzelne 1-polige LS bzw. Sicherungen (je eine Phase)
 * statt eines mehrpoligen Geräts, z. B. für einen CEE-rot-Abgang.
 * $phase: angeschlossene Phase eines einphasigen Schutzorgans (1- oder
 * 2-polig, nicht $threeSingle).
 */
final class ProtectiveDevice
{
    public const RCD_TYPES = ['AC', 'A', 'F', 'B', 'B+'];
    public const RESIDUAL_CURRENTS_MA = [10, 30, 100, 300, 500];
    public const CHARACTERISTICS = ['B', 'C', 'D', 'K', 'Z'];
    public const RATED_CURRENTS_A = [2, 4, 6, 10, 13, 16, 20, 25, 32, 40, 50, 63, 80, 100, 125];
    public const POLES = [1, 2, 3, 4];
    public const PHASES = ['L1', 'L2', 'L3'];
    public const FUSE_SYSTEMS = ['neozed' => 'Neozed', 'diazed' => 'Diazed'];
    /** Übliche Nennströme von Neozed-/Diazed-Sicherungseinsätzen (gG). */
    public const FUSE_RATED_CURRENTS_A = [2, 4, 6, 10, 13, 16, 20, 25, 32, 35, 50, 63];

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
        public ?string $fuseSystem = null,
        public bool $threeSingle = false,
        public ?string $phase = null,
    ) {
    }

    /** Versorgt dreiphasig (3/4-polig oder drei einzelne)? null = Polzahl unbekannt. */
    public function suppliesThreePhase(): ?bool
    {
        if ($this->threeSingle) {
            return true;
        }

        return $this->poles !== null ? $this->poles >= 3 : null;
    }

    /** Einphasig (1- oder 2-polig, keine drei einzelne)? Dann kann eine Phase angegeben werden. */
    public function isSinglePhase(): bool
    {
        return !$this->threeSingle && $this->poles !== null && $this->poles <= 2;
    }

    /** Anzahl Sicherungseinsätze (für den Hinweis auf Ersatzsicherungen). */
    public function fuseCount(): int
    {
        return $this->type === ProtectiveDeviceType::Fuse ? ($this->threeSingle ? 3 : 1) : 0;
    }

    /** z. B. "Neozed 16 A" */
    public function fuseDescription(): ?string
    {
        if ($this->type !== ProtectiveDeviceType::Fuse) {
            return null;
        }

        return (self::FUSE_SYSTEMS[$this->fuseSystem ?? ''] ?? 'Schmelzsicherung') . ' ' . ($this->ratedCurrentA ?? '?') . ' A';
    }

    /** Technische Kurzbeschreibung, z. B. "FI Typ A 30 mA, 40 A, 4-polig", "LS C16, 1-polig, L2" oder "Neozed 16 A, 3 × 1-polig". */
    public function specification(): string
    {
        if ($this->type === ProtectiveDeviceType::Fuse) {
            return $this->fuseDescription() . ', ' . $this->polesText();
        }

        $parts = [];
        if ($this->type->hasResidualProtection()) {
            $parts[] = 'Typ ' . ($this->rcdType ?? '?') . ' ' . ($this->residualCurrentMa ?? '?') . ' mA';
        }
        if ($this->type->hasOvercurrentProtection()) {
            $parts[] = ($this->characteristic ?? '?') . ($this->ratedCurrentA ?? '?');
        } elseif ($this->ratedCurrentA !== null) {
            $parts[] = $this->ratedCurrentA . ' A';
        }
        $poles = $this->polesText();
        if ($poles !== '') {
            $parts[] = $poles;
        }

        return $this->type->shortLabel() . ' ' . implode(', ', $parts);
    }

    private function polesText(): string
    {
        if ($this->threeSingle) {
            return '3 × 1-polig (L1/L2/L3)';
        }
        if ($this->poles === null) {
            return '';
        }

        return $this->poles . '-polig' . ($this->phase !== null && $this->isSinglePhase() ? ', ' . $this->phase : '');
    }
}
