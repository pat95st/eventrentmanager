<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\PowerPlan\RentalPowerSetupLoader;
use App\Domain\PowerPlan\PowerRebalancer;
use App\Domain\PowerPlan\RentalPowerPlanRepositoryInterface;

final class ApplyRentalPowerSuggestionsHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalPowerSetupLoader $loader,
        private readonly RentalPowerPlanRepositoryInterface $plans,
    ) {
    }

    /** @return int number of applied moves */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ApplyRentalPowerSuggestions);
        $setup = $this->loader->load($command->rentalId);
        $plan = $setup->plan;
        ['moves' => $moves] = (new PowerRebalancer())->suggest($setup->nodes, $setup->venueConnections, $plan->assignments, $plan->simultaneityFactor);

        $applied = 0;
        foreach ($moves as $move) {
            if ($command->keys !== null && !in_array($move->key, $command->keys, true)) {
                continue;
            }
            $this->plans->saveAssignment($plan->rentalId, $move->to);
            $applied++;
        }

        return $applied;
    }
}
