<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Dashboard\DashboardTemplate;
use App\Domain\Dashboard\DashboardTemplateRepositoryInterface;

/** In-Memory-Test-Double für DashboardTemplateRepositoryInterface (siehe tests/README.md). */
final class InMemoryDashboardTemplateRepository implements DashboardTemplateRepositoryInterface
{
    /** @var array<string, DashboardTemplate> */
    private array $templates = [];

    public function findAll(): array
    {
        $templates = array_values($this->templates);
        usort($templates, static fn (DashboardTemplate $a, DashboardTemplate $b) => $a->name <=> $b->name);

        return $templates;
    }

    public function findById(string $id): ?DashboardTemplate
    {
        return $this->templates[$id] ?? null;
    }

    public function save(DashboardTemplate $template): void
    {
        $this->templates[$template->id] = $template;
    }

    public function delete(string $id): void
    {
        unset($this->templates[$id]);
    }
}
