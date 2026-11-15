<?php

declare(strict_types=1);

namespace App\Domain\PowerPlan;

interface RentalPowerPlanRepositoryInterface
{
    /** Returns an empty plan (default factor, no assignments) if none was saved yet. */
    public function findByRental(string $rentalId): RentalPowerPlan;

    /** Saves the factor and replaces all assignments of the event. */
    public function save(RentalPowerPlan $plan): void;

    /** Saves a single assignment (insert or replace). */
    public function saveAssignment(string $rentalId, PowerAssignment $assignment): void;

    /** Input power of an article in watts (null = none/unknown). */
    public function inputPowerOf(string $articleId): ?int;

    /**
     * @param string[] $articleIds
     * @return array<string, int> article ID => input power in watts (only articles with a value)
     */
    public function inputPowers(array $articleIds): array;

    public function saveInputPower(string $articleId, ?int $watts): void;
}
