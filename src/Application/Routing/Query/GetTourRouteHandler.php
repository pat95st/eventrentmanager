<?php

declare(strict_types=1);

namespace App\Application\Routing\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\Routing\TourRoute;
use App\Application\Routing\TourRouteService;

final class GetTourRouteHandler implements QueryHandlerInterface
{
    public function __construct(private readonly TourRouteService $service)
    {
    }

    public function handle(QueryInterface $query): TourRoute
    {
        assert($query instanceof GetTourRoute);

        return $this->service->forRental($query->rentalId);
    }
}
