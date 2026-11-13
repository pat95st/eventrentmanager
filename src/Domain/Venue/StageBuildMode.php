<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/** Wie eine mobile Bühne beschrieben ist: über feste Maße oder über ihren Bestand an Bühnenelementen. */
enum StageBuildMode: string
{
    case Dimensions = 'dimensions';
    case Elements = 'elements';
}
