<?php

declare(strict_types=1);

namespace App\Domain\RevenueGoal;

enum RevenueGoalKind: string
{
    /** Ziel, das erreicht werden soll (z. B. Jahresumsatz, Liebhabereigrenze). */
    case Target = 'target';

    /** Grenze, die nicht überschritten werden soll (z. B. Kleinunternehmergrenze). */
    case Limit = 'limit';
}
