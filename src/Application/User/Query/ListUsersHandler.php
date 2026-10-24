<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\User\UserRepositoryInterface;

final class ListUsersHandler implements QueryHandlerInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListUsers);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $items = $this->users->findAll($query->pageSize, $offset);
        $totalCount = $this->users->countAll();

        return new PaginatedResult($items, $totalCount, $query->page, $query->pageSize);
    }
}
