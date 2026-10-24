<?php

declare(strict_types=1);

namespace App\Application\Venue\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Venue\VenuePowerConnectionRepositoryInterface;

final class ListVenuePowerConnectionsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VenuePowerConnectionRepositoryInterface $connections)
    {
    }

    /** @return \App\Domain\Venue\PowerConnection[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListVenuePowerConnections);

        return $this->connections->findByVenue($query->venueId);
    }
}
