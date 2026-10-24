<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\DocumentStorage\DocumentStorageAssignmentRepositoryInterface;

final class ListStorageAssignmentsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DocumentStorageAssignmentRepositoryInterface $assignments)
    {
    }

    public function handle(QueryInterface $query): mixed
    {
        assert($query instanceof ListStorageAssignments);

        return $this->assignments->findAll();
    }
}
