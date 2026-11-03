<?php

declare(strict_types=1);

namespace App\Domain\PowerDistributor;

/** Steckverbindungen für Zuleitung und Abgänge eines Stromverteilers (Migration 117). */
enum ConnectorType: string
{
    case CeeRed16 = 'cee_red_16';
    case CeeRed32 = 'cee_red_32';
    case CeeRed63 = 'cee_red_63';
    case CeeRed125 = 'cee_red_125';
    case CeeBlue16 = 'cee_blue_16';
    case CeeBlue32 = 'cee_blue_32';
    case CeeBlue63 = 'cee_blue_63';
    case Schuko = 'schuko';
    case Powercon = 'powercon';
    case PowerconTrue1 = 'powercon_true1';

    public function label(): string
    {
        return match ($this) {
            self::CeeRed16 => 'CEE rot 16 A (400 V)',
            self::CeeRed32 => 'CEE rot 32 A (400 V)',
            self::CeeRed63 => 'CEE rot 63 A (400 V)',
            self::CeeRed125 => 'CEE rot 125 A (400 V)',
            self::CeeBlue16 => 'CEE blau 16 A (230 V)',
            self::CeeBlue32 => 'CEE blau 32 A (230 V)',
            self::CeeBlue63 => 'CEE blau 63 A (230 V)',
            self::Schuko => 'Schuko',
            self::Powercon => 'powerCON',
            self::PowerconTrue1 => 'powerCON TRUE1',
        };
    }

    /** Kurzform für Schemata und Listen, z. B. "CEE 32 A rot". */
    public function shortLabel(): string
    {
        return match ($this) {
            self::CeeRed16, self::CeeRed32, self::CeeRed63, self::CeeRed125 => 'CEE ' . substr($this->value, 8) . ' A rot',
            self::CeeBlue16, self::CeeBlue32, self::CeeBlue63 => 'CEE ' . substr($this->value, 9) . ' A blau',
            default => $this->label(),
        };
    }

    /** Farbe für die Darstellung (Tailwind-unabhängig, als CSS-Farbe). */
    public function color(): string
    {
        return match ($this) {
            self::CeeRed16, self::CeeRed32, self::CeeRed63, self::CeeRed125 => '#dc2626',
            self::CeeBlue16, self::CeeBlue32, self::CeeBlue63 => '#2563eb',
            self::Schuko => '#475569',
            self::Powercon => '#0ea5e9',
            self::PowerconTrue1 => '#1e293b',
        };
    }
}
