<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;

final class ListStorageTargetsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DocumentStorageTargetRepositoryInterface $targets)
    {
    }

    public function handle(QueryInterface $query): mixed
    {
        assert($query instanceof ListStorageTargets);

        return $this->targets->findAll();
    }
}
