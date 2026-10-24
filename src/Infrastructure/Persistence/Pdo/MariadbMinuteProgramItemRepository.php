<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\MinuteProgram\MinuteProgramItem;
use App\Domain\MinuteProgram\MinuteProgramItemRepositoryInterface;
use PDO;

final class MariadbMinuteProgramItemRepository implements MinuteProgramItemRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(MinuteProgramItem $item): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO minute_program_items (
                id, minute_program_id, sort_order, name, description,
                note_general, note_sound, note_light, note_video, note_stage, duration_minutes
             ) VALUES (
                :id, :minute_program_id, :sort_order, :name, :description,
                :note_general, :note_sound, :note_light, :note_video, :note_stage, :duration_minutes
             )
             ON DUPLICATE KEY UPDATE
                sort_order = :sort_order2, name = :name2, description = :description2,
                note_general = :note_general2, note_sound = :note_sound2, note_light = :note_light2,
                note_video = :note_video2, note_stage = :note_stage2, duration_minutes = :duration_minutes2'
        );

        $stmt->execute([
            'id' => $item->id,
            'minute_program_id' => $item->minuteProgramId,
            'sort_order' => $item->sortOrder,
            'name' => $item->name,
            'description' => $item->description,
            'note_general' => $item->noteGeneral,
            'note_sound' => $item->noteSound,
            'note_light' => $item->noteLight,
            'note_video' => $item->noteVideo,
            'note_stage' => $item->noteStage,
            'duration_minutes' => $item->durationMinutes,
            'sort_order2' => $item->sortOrder,
            'name2' => $item->name,
            'description2' => $item->description,
            'note_general2' => $item->noteGeneral,
            'note_sound2' => $item->noteSound,
            'note_light2' => $item->noteLight,
            'note_video2' => $item->noteVideo,
            'note_stage2' => $item->noteStage,
            'duration_minutes2' => $item->durationMinutes,
        ]);
    }

    public function findById(string $id): ?MinuteProgramItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM minute_program_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByMinuteProgram(string $minuteProgramId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM minute_program_items WHERE minute_program_id = :minute_program_id ORDER BY sort_order ASC');
        $stmt->execute(['minute_program_id' => $minuteProgramId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function nextSortOrder(string $minuteProgramId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM minute_program_items WHERE minute_program_id = :minute_program_id');
        $stmt->execute(['minute_program_id' => $minuteProgramId]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM minute_program_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): MinuteProgramItem
    {
        return new MinuteProgramItem(
            id: $row['id'],
            minuteProgramId: $row['minute_program_id'],
            sortOrder: (int) $row['sort_order'],
            name: $row['name'],
            description: $row['description'],
            noteGeneral: $row['note_general'],
            noteSound: $row['note_sound'],
            noteLight: $row['note_light'],
            noteVideo: $row['note_video'],
            noteStage: $row['note_stage'],
            durationMinutes: $row['duration_minutes'] !== null ? (int) $row['duration_minutes'] : null,
        );
    }
}
