<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdatePriceTiers implements CommandInterface
{
    /**
     * Tag 1 ist per Definition immer 100 % und wird hier bewusst nicht
     * mitgegeben (siehe UpdatePriceTiersHandler).
     *
     * @param int $maxDays Anzahl der Staffel-Tage insgesamt (inkl. Tag 1); bestimmt, wie viele Einträge aus $percentages verwendet werden
     * @param array<int, string> $percentages Einsatztage (2..$maxDays) => eingegebener Prozentwert als String
     */
    public function __construct(
        public readonly int $maxDays,
        public readonly array $percentages,
    ) {
    }
}
