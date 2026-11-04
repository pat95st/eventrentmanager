<?php

declare(strict_types=1);

namespace App\Application\Rack\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rack\RackRepositoryInterface;

final class GetRackHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RackRepositoryInterface $racks,
        private readonly RackDetailBuilder $builder,
    ) {
    }

    public function handle(QueryInterface $query): RackDetail
    {
        if ($query instanceof GetRackByExemplar) {
            $exemplar = $this->racks->findExemplarById($query->exemplarId);
            $rack = $exemplar !== null ? $this->racks->findById($exemplar->rackId) : null;
            if ($rack === null) {
                throw new NotFoundException('Rack wurde nicht gefunden.');
            }

            return $this->builder->build($rack);
        }
        assert($query instanceof GetRack);
        $rack = $this->racks->findById($query->id);
        if ($rack === null) {
            throw new NotFoundException('Rack wurde nicht gefunden.');
        }

        return $this->builder->build($rack);
    }
}
