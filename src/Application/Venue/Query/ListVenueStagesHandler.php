<?php

declare(strict_types=1);

namespace App\Application\Venue\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Venue\VenueStageRepositoryInterface;

final class ListVenueStagesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VenueStageRepositoryInterface $stages)
    {
    }

    /** @return \App\Domain\Venue\Stage[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListVenueStages);

        return $this->stages->findByVenue($query->venueId);
    }
}
