<?php

declare(strict_types=1);

namespace App\Application\Venue\Query;

use App\Application\Query\QueryInterface;

/** Riser aller Bühnen eines Veranstaltungsorts, gruppiert nach Bühne. */
final class ListVenueStageRisers implements QueryInterface
{
    public function __construct(public readonly string $venueId)
    {
    }
}
