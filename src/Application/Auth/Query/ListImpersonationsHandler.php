<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Auth\ImpersonationRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;

final class ListImpersonationsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ImpersonationRepositoryInterface $impersonations,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListImpersonations);

        $offset = $query->pageSize !== null ? PaginatedResult::offsetFor($query->page ?? 1, $query->pageSize) : null;

        $impersonations = $this->impersonations->findAll($query->pageSize, $offset);
        $totalCount = $this->impersonations->countAll();

        $rows = array_map(function ($impersonation) {
            $admin = $this->users->findById($impersonation->adminUserId);
            $target = $this->users->findById($impersonation->targetUserId);

            return new ImpersonationListRow(
                $impersonation,
                $admin?->displayName(),
                $target?->displayName(),
            );
        }, $impersonations);

        return new PaginatedResult($rows, $totalCount, $query->page ?? 1, $query->pageSize ?? max($totalCount, 1));
    }
}
