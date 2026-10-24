<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\MinuteProgram\MinuteProgramTemplate;
use App\Domain\MinuteProgram\MinuteProgramTemplateRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbMinuteProgramTemplateRepository implements MinuteProgramTemplateRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(MinuteProgramTemplate $template): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO minute_program_templates (
                id, name, jingle_before, handheld_mic_count, headset_mic_count, created_at, updated_at
             ) VALUES (
                :id, :name, :jingle_before, :handheld_mic_count, :headset_mic_count, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                name = :name2, jingle_before = :jingle_before2, handheld_mic_count = :handheld_mic_count2,
                headset_mic_count = :headset_mic_count2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $template->id,
            'name' => $template->name,
            'jingle_before' => $template->jingleBefore ? 1 : 0,
            'handheld_mic_count' => $template->handheldMicCount,
            'headset_mic_count' => $template->headsetMicCount,
            'created_at' => $template->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $template->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $template->name,
            'jingle_before2' => $template->jingleBefore ? 1 : 0,
            'handheld_mic_count2' => $template->handheldMicCount,
            'headset_mic_count2' => $template->headsetMicCount,
            'updated_at2' => $template->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?MinuteProgramTemplate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM minute_program_templates WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM minute_program_templates ORDER BY name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM minute_program_templates WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): MinuteProgramTemplate
    {
        return new MinuteProgramTemplate(
            id: $row['id'],
            name: $row['name'],
            jingleBefore: (bool) $row['jingle_before'],
            handheldMicCount: (int) $row['handheld_mic_count'],
            headsetMicCount: (int) $row['headset_mic_count'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
