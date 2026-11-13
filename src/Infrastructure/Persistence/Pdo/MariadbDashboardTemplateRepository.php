<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\DashboardTemplate;
use App\Domain\Dashboard\DashboardTemplateRepositoryInterface;
use PDO;

final class MariadbDashboardTemplateRepository implements DashboardTemplateRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT id, name, description, layout FROM dashboard_templates ORDER BY name ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);

        return array_map(self::hydrate(...), $rows);
    }

    public function findById(string $id): ?DashboardTemplate
    {
        $stmt = $this->pdo->prepare('SELECT id, name, description, layout FROM dashboard_templates WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function save(DashboardTemplate $template): void
    {
        $this->pdo->prepare(
            'INSERT INTO dashboard_templates (id, name, description, layout, created_at, updated_at)
             VALUES (:id, :name, :description, :layout, NOW(), NOW())
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), layout = VALUES(layout), updated_at = NOW()'
        )->execute([
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'layout' => $template->layout->toJson(),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM dashboard_templates WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): DashboardTemplate
    {
        return new DashboardTemplate(
            (string) $row['id'],
            (string) $row['name'],
            $row['description'] !== null && $row['description'] !== '' ? (string) $row['description'] : null,
            DashboardLayout::fromJson((string) $row['layout']) ?? DashboardLayout::fromZones([]),
        );
    }
}
