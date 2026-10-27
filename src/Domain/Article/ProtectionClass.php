<?php

declare(strict_types=1);

namespace App\Domain\Article;

/**
 * Schutzklasse (DIN VDE 0106/0140) eines Artikels, der elektrisch geprüft
 * werden muss (siehe Article::$requiresElectricalInspection). Nur relevant/
 * gepflegt, wenn requiresElectricalInspection = true — bei false bleibt
 * Article::$protectionClass immer null (siehe CreateArticleHandler/
 * UpdateArticleHandler).
 *
 * Wird für den bei jeder Elektroprüfung einzeln erfassten Isolationswiderstand
 * benötigt, da dessen Mindestwert je Schutzklasse unterschiedlich ist (siehe
 * ElectricalInspection::measurementWarnings()).
 */
enum ProtectionClass: string
{
    case I = 'I';     // Schutzerdung (Schutzleiter vorhanden)
    case II = 'II';    // Schutzisolierung (kein Schutzleiter)
    case III = 'III';   // Schutzkleinspannung (SELV/PELV)

    public function label(): string
    {
        return match ($this) {
            self::I => 'Schutzklasse I (Schutzerdung)',
            self::II => 'Schutzklasse II (schutzisoliert)',
            self::III => 'Schutzklasse III (Schutzkleinspannung)',
        };
    }

    /** @return ProtectionClass[] Alle Werte in fester Anzeigereihenfolge. */
    public static function all(): array
    {
        return [self::I, self::II, self::III];
    }
}
