<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\ClosingTextTemplate\ClosingTextTemplate;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbClosingTextTemplateRepository implements ClosingTextTemplateRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ClosingTextTemplate $template): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO closing_text_templates (id, folder_id, name, content, created_at, updated_at)
             VALUES (:id, :folder_id, :name, :content, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE folder_id = :folder_id2, name = :name2, content = :content2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $template->id,
            'folder_id' => $template->folderId,
            'name' => $template->name,
            'content' => $template->content,
            'created_at' => $template->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $template->updatedAt->format('Y-m-d H:i:s'),
            'folder_id2' => $template->folderId,
            'name2' => $template->name,
            'content2' => $template->content,
            'updated_at2' => $template->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?ClosingTextTemplate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM closing_text_templates WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM closing_text_templates ORDER BY name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByFolder(?string $folderId): array
    {
        if ($folderId === null) {
            $stmt = $this->pdo->prepare('SELECT * FROM closing_text_templates WHERE folder_id IS NULL ORDER BY name ASC');
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM closing_text_templates WHERE folder_id = :folder_id ORDER BY name ASC');
            $stmt->execute(['folder_id' => $folderId]);
        }

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countByFolder(string $folderId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM closing_text_templates WHERE folder_id = :folder_id');
        $stmt->execute(['folder_id' => $folderId]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM closing_text_templates WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): ClosingTextTemplate
    {
        return new ClosingTextTemplate(
            id: $row['id'],
            folderId: $row['folder_id'],
            name: $row['name'],
            content: $row['content'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
