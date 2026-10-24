<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\PriceTier;
use App\Domain\Settings\PriceTierRepositoryInterface;

final class GetPriceTiersHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PriceTierRepositoryInterface $priceTiers)
    {
    }

    /** @return PriceTier[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof GetPriceTiers);

        return $this->priceTiers->getAll();
    }
}
