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

    /** Peak and average power of an article (both null = none/unknown). */
    public function articlePowerOf(string $articleId): ArticlePower;

    /**
     * @param string[] $articleIds
     * @return array<string, ArticlePower> article ID => power (only articles with at least one value)
     */
    public function articlePowers(array $articleIds): array;

    public function saveArticlePower(string $articleId, ArticlePower $power): void;
}
