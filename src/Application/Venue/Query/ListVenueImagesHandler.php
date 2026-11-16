<?php

declare(strict_types=1);

namespace App\Application\Venue\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Venue\VenueImageRepositoryInterface;

final class ListVenueImagesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VenueImageRepositoryInterface $images)
    {
    }

    /** @return \App\Domain\Venue\VenueImage[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListVenueImages);

        return $this->images->findByVenue($query->venueId);
    }
}
