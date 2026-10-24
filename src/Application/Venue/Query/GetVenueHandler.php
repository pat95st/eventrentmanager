<?php

declare(strict_types=1);

namespace App\Application\Venue\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Venue\Venue;
use App\Domain\Venue\VenueRepositoryInterface;

final class GetVenueHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VenueRepositoryInterface $venues)
    {
    }

    public function handle(QueryInterface $query): Venue
    {
        assert($query instanceof GetVenue);

        $venue = $this->venues->findById($query->id);
        if ($venue === null) {
            throw new NotFoundException("Veranstaltungsort \"{$query->id}\" wurde nicht gefunden.");
        }

        return $venue;
    }
}
