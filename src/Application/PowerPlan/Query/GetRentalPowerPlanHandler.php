<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Query;

use App\Application\PowerPlan\PowerTargetOptions;
use App\Application\PowerPlan\RentalPowerSetupLoader;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\PowerPlan\PowerLoadCalculator;
use App\Domain\PowerPlan\PowerRebalancer;

final class GetRentalPowerPlanHandler implements QueryHandlerInterface
{
    public function __construct(private readonly RentalPowerSetupLoader $loader)
    {
    }

    public function handle(QueryInterface $query): RentalPowerPlanView
    {
        assert($query instanceof GetRentalPowerPlan);
        $setup = $this->loader->load($query->rentalId);
        $plan = $setup->plan;

        $result = (new PowerLoadCalculator())->calculate($setup->nodes, $setup->venueConnections, $plan->assignments, $plan->simultaneityFactor);
        $suggestions = [];
        $after = $result;
        if ($result->hasOverloads()) {
            ['moves' => $suggestions, 'result' => $after] = (new PowerRebalancer())->suggest($setup->nodes, $setup->venueConnections, $plan->assignments, $plan->simultaneityFactor);
        }

        return new RentalPowerPlanView($setup, $result, $suggestions, $after, new PowerTargetOptions($setup));
    }
}
