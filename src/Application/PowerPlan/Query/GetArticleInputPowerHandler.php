<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\PowerPlan\RentalPowerPlanRepositoryInterface;

final class GetArticleInputPowerHandler implements QueryHandlerInterface
{
    public function __construct(private readonly RentalPowerPlanRepositoryInterface $plans)
    {
    }

    public function handle(QueryInterface $query): ?int
    {
        assert($query instanceof GetArticleInputPower);

        return $this->plans->inputPowerOf($query->articleId);
    }
}
