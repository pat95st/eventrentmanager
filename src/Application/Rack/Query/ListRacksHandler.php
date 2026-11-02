<?php

declare(strict_types=1);

namespace App\Application\Rack\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Rack\RackRepositoryInterface;

final class ListRacksHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RackRepositoryInterface $racks,
        private readonly RackDetailBuilder $builder,
    ) {
    }

    /** @return RackDetail[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRacks);

        return array_map(fn ($rack) => $this->builder->build($rack), $this->racks->findAll($query->search));
    }
}
