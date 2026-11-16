<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\PowerPlan\ArticlePower;
use App\Domain\PowerPlan\RentalPowerPlanRepositoryInterface;

final class GetArticleInputPowerHandler implements QueryHandlerInterface
{
    public function __construct(private readonly RentalPowerPlanRepositoryInterface $plans)
    {
    }

    public function handle(QueryInterface $query): ArticlePower
    {
        assert($query instanceof GetArticleInputPower);

        return $this->plans->articlePowerOf($query->articleId);
    }
}
