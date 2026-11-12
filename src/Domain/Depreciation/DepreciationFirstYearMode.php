<?php

declare(strict_types=1);

namespace App\Domain\Depreciation;

/** Wie das Anschaffungsjahr in der linearen Abschreibung behandelt wird. */
enum DepreciationFirstYearMode: string
{
    /** Monatsgenau (pro rata temporis): der Anschaffungsmonat zählt voll mit. */
    case Monthly = 'monthly';

    /** Volle Jahresrate schon im Anschaffungsjahr, unabhängig vom Monat. */
    case FullYear = 'full_year';
}
