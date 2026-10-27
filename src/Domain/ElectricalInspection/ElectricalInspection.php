<?php

declare(strict_types=1);

namespace App\Domain\ElectricalInspection;

use App\Domain\Article\ProtectionClass;
use DateInterval;
use DateTimeImmutable;

final class ElectricalInspection
{
    /**
     * Grenzwerte der vier einzeln erfassten Messwerte (siehe measurementWarnings()).
     * Als Strings geführt (siehe Article::$weightValue für dieselbe Begründung:
     * Vermeidung von Rundungsfehlern bei den DECIMAL-Spalten).
     *
     * HINWEIS zu den gesetzlich/normativ etwas komplexeren Grenzwerten — hier
     * bewusst vereinfacht auf den jeweiligen Normalfall, da die für die
     * Ausnahmen nötigen Zusatzangaben (Leitungslänge, Heizleistung in kW,
     * Messverfahren) aktuell nicht erfasst werden:
     *   - Schutzleiterwiderstand: der tatsächliche Grenzwert ist
     *     leitungslängenabhängig (0,3 Ω bis 5 m, +0,1 Ω je weitere 7,5 m);
     *     hier wird nur der davon unabhängige ABSOLUTE Grenzwert (1,0 Ω)
     *     geprüft, die längenabhängige Feinprüfung bleibt Aufgabe des Prüfers.
     *   - Schutzleiterstrom/Ableitstrom: 3,5 mA, AUSSER bei Geräten mit
     *     Heizleistung > 3,5 kW (dort 1,0 mA je kW) — diese Ausnahme wird
     *     hier nicht automatisch berücksichtigt.
     *   - Berührungsstrom: 0,5 mA, beim Ersatzableitstromverfahren 0,25 mA —
     *     hier wird einheitlich der unkritischere Wert (0,5 mA) geprüft.
     */
    private const MAX_PROTECTIVE_CONDUCTOR_RESISTANCE_OHM = 1.0;
    private const MAX_PROTECTIVE_CONDUCTOR_CURRENT_MA = 3.5;
    private const MAX_TOUCH_CURRENT_MA = 0.5;

    public function __construct(
        public readonly string $id,
        public readonly string $inventoryNumberId,
        public DateTimeImmutable $inspectedAt,
        public int $validityYears,   // 1 oder 2
        public bool $passed,
        public ?string $inspector,
        // Schutzleiterwiderstand in Ω; nur bei Schutzklasse I sinnvoll (siehe Hinweis oben)
        public ?string $protectiveConductorResistanceOhm,
        // Isolationswiderstand in MΩ; bei allen Schutzklassen relevant, Mindestwert je Klasse unterschiedlich
        public ?string $insulationResistanceMohm,
        // Schutzleiterstrom/Ableitstrom in mA; nur bei Schutzklasse I sinnvoll
        public ?string $protectiveConductorCurrentMa,
        // Berührungsstrom in mA; bei Schutzklasse II bzw. isolierten Teilen einer Schutzklasse-I-Installation relevant
        public ?string $touchCurrentMa,
        public ?string $notes,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function validUntil(): DateTimeImmutable
    {
        return $this->inspectedAt->add(new DateInterval('P' . $this->validityYears . 'Y'));
    }

    public function status(DateTimeImmutable $today = new DateTimeImmutable('today')): ElectricalInspectionStatus
    {
        return ElectricalInspectionStatus::compute($this->passed, $this->validUntil(), $today);
    }

    /**
     * Menschlich lesbare Warnungen für Messwerte, die den (vereinfachten,
     * siehe Klassen-Kommentar) Grenzwert über-/unterschreiten. Nur Werte,
     * die tatsächlich erfasst wurden, werden geprüft — ein leeres Feld löst
     * keine Warnung aus. $articleProtectionClass ist die am Artikel
     * gepflegte Schutzklasse (Article::$protectionClass), wird nur für den
     * Isolationswiderstand-Mindestwert benötigt, da dieser je Schutzklasse
     * unterschiedlich ist; ist sie unbekannt (null), wird dieser eine Wert
     * nicht geprüft.
     *
     * @return string[]
     */
    public function measurementWarnings(?ProtectionClass $articleProtectionClass): array
    {
        $warnings = [];

        if (
            $this->protectiveConductorResistanceOhm !== null
            && (float) $this->protectiveConductorResistanceOhm > self::MAX_PROTECTIVE_CONDUCTOR_RESISTANCE_OHM
        ) {
            $warnings[] = sprintf(
                'Schutzleiterwiderstand (%s Ω) überschreitet den absoluten Grenzwert von %s Ω.',
                $this->protectiveConductorResistanceOhm,
                self::formatNumber(self::MAX_PROTECTIVE_CONDUCTOR_RESISTANCE_OHM)
            );
        }

        if ($this->insulationResistanceMohm !== null && $articleProtectionClass !== null) {
            $minMohm = match ($articleProtectionClass) {
                ProtectionClass::I => 1.0,
                ProtectionClass::II => 2.0,
                ProtectionClass::III => 0.25,
            };

            if ((float) $this->insulationResistanceMohm < $minMohm) {
                $warnings[] = sprintf(
                    'Isolationswiderstand (%s MΩ) unterschreitet den Mindestwert von %s MΩ für %s.',
                    $this->insulationResistanceMohm,
                    self::formatNumber($minMohm),
                    $articleProtectionClass->label()
                );
            }
        }

        if (
            $this->protectiveConductorCurrentMa !== null
            && (float) $this->protectiveConductorCurrentMa > self::MAX_PROTECTIVE_CONDUCTOR_CURRENT_MA
        ) {
            $warnings[] = sprintf(
                'Schutzleiterstrom/Ableitstrom (%s mA) überschreitet den Grenzwert von %s mA.',
                $this->protectiveConductorCurrentMa,
                self::formatNumber(self::MAX_PROTECTIVE_CONDUCTOR_CURRENT_MA)
            );
        }

        if ($this->touchCurrentMa !== null && (float) $this->touchCurrentMa > self::MAX_TOUCH_CURRENT_MA) {
            $warnings[] = sprintf(
                'Berührungsstrom (%s mA) überschreitet den Grenzwert von %s mA.',
                $this->touchCurrentMa,
                self::formatNumber(self::MAX_TOUCH_CURRENT_MA)
            );
        }

        return $warnings;
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }
}
