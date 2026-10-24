<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/**
 * Anschlussart eines Stromanschlusses an einem Veranstaltungsort.
 *
 * WICHTIG — Bemessungsstrom-Tabelle (ratedCurrentAmps()): jede Anschlussart
 * ist bauartbedingt auf einen maximalen Strom ausgelegt (Steckverbinder-Norm).
 * Diese Tabelle ist zentral an EINER Stelle gepflegt, damit sie bei Bedarf
 * leicht korrigiert werden kann:
 *
 *   230 V Schuko       → 16 A (Standard-Schuko-Steckdose)
 *   230 V CEE 16 A      → 16 A
 *   230 V CEE 32 A      → 32 A
 *   400 V CEE 16 A      → 16 A
 *   400 V CEE 32 A      → 32 A
 *   400 V CEE 63 A      → 63 A
 *   400 V CEE 125 A     → 125 A
 *   Festanschluss        → kein Bemessungsstrom (siehe ratedCurrentAmps())
 *
 * Der "Bemessungsstrom" dient als Vergleichswert für die Warnung
 * "Leitungsschutzschalter größer als Anschlusstyp zulässt" (siehe
 * PowerConnection::breakerExceedsRatedCurrent()).
 */
enum PowerConnectionType: string
{
    case Schuko230V = '230 V Schuko';
    case Cee16A230V = '230 V CEE 16 A';
    case Cee32A230V = '230 V CEE 32 A';
    case Cee16A400V = '400 V CEE 16 A';
    case Cee32A400V = '400 V CEE 32 A';
    case Cee63A400V = '400 V CEE 63 A';
    case Cee125A400V = '400 V CEE 125 A';
    case Festanschluss = 'Festanschluss';

    /**
     * Bauartbedingter Bemessungsstrom in Ampere, oder null, wenn die
     * Anschlussart keinen sinnvollen Vergleichswert hat (Festanschluss —
     * dessen Belastbarkeit ergibt sich aus der individuellen Installation/dem
     * Leitungsschutzschalter, nicht aus einer Steckverbinder-Norm). Bei null
     * wird die Warnung "Leitungsschutzschalter zu groß" nicht berechnet.
     */
    public function ratedCurrentAmps(): ?int
    {
        return match ($this) {
            self::Schuko230V => 16,
            self::Cee16A230V => 16,
            self::Cee32A230V => 32,
            self::Cee16A400V => 16,
            self::Cee32A400V => 32,
            self::Cee63A400V => 63,
            self::Cee125A400V => 125,
            self::Festanschluss => null,
        };
    }

    /** @return PowerConnectionType[] Alle Anschlussarten in fester Anzeigereihenfolge. */
    public static function all(): array
    {
        return [
            self::Schuko230V,
            self::Cee16A230V,
            self::Cee32A230V,
            self::Cee16A400V,
            self::Cee32A400V,
            self::Cee63A400V,
            self::Cee125A400V,
            self::Festanschluss,
        ];
    }
}
