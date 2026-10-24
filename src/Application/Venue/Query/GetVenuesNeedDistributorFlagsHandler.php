<?php

declare(strict_types=1);

namespace App\Application\Venue\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Venue\PowerConnection;
use App\Domain\Venue\VenuePowerConnectionRepositoryInterface;

final class GetVenuesNeedDistributorFlagsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VenuePowerConnectionRepositoryInterface $connections)
    {
    }

    /** @return array<string, bool> venueId => needsDistributorWithRcd */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof GetVenuesNeedDistributorFlags);

        $grouped = $this->connections->findByVenues($query->venueIds);

        $flags = [];
        foreach ($grouped as $venueId => $connections) {
            $flags[$venueId] = PowerConnection::anyNeedsDistributorWithRcd($connections);
        }

        return $flags;
    }
}
