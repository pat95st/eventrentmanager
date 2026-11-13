<?php

declare(strict_types=1);

namespace App\Application\Venue\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Venue\VenueStageRiserRepositoryInterface;

final class ListVenueStageRisersHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VenueStageRiserRepositoryInterface $risers)
    {
    }

    /** @return array<string, \App\Domain\Venue\Riser[]> Bühnen-ID => Riser */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListVenueStageRisers);

        return $this->risers->findByVenueGroupedByStage($query->venueId);
    }
}
