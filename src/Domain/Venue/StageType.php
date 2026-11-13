<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/** Bauart einer Bühne: fest im Veranstaltungsort installiert oder mobil (z. B. Podeste, Trailerbühne). */
enum StageType: string
{
    case Fixed = 'fixed';
    case Mobile = 'mobile';

    /** Übersetzungsschlüssel der Bezeichnung (siehe resources/lang). */
    public function labelKey(): string
    {
        return 'venues.stages.type.' . $this->value;
    }
}
