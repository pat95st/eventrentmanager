<?php

declare(strict_types=1);

namespace App\Application\RevenueGoal;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\RevenueGoal\RevenueGoalRepositoryInterface;

final class ListRevenueGoalsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly RevenueGoalRepositoryInterface $goals)
    {
    }

    /** @return \App\Domain\RevenueGoal\RevenueGoal[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRevenueGoals);

        return $this->goals->findAll();
    }
}
