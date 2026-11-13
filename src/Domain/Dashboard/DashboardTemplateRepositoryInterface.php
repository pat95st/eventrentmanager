<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

interface DashboardTemplateRepositoryInterface
{
    /** @return DashboardTemplate[] sortiert nach Name */
    public function findAll(): array;

    public function findById(string $id): ?DashboardTemplate;

    public function save(DashboardTemplate $template): void;

    public function delete(string $id): void;
}
