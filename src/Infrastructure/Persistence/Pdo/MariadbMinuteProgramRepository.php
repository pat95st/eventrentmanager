<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbMinuteProgramRepository implements MinuteProgramRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(MinuteProgram $program): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO minute_programs (
                id, rental_id, name, room, start_at, jingle_before, handheld_mic_count,
                headset_mic_count, current_delay_minutes, created_at, updated_at
             ) VALUES (
                :id, :rental_id, :name, :room, :start_at, :jingle_before, :handheld_mic_count,
                :headset_mic_count, :current_delay_minutes, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                name = :name2, room = :room2, start_at = :start_at2, jingle_before = :jingle_before2,
                handheld_mic_count = :handheld_mic_count2, headset_mic_count = :headset_mic_count2,
                current_delay_minutes = :current_delay_minutes2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $program->id,
            'rental_id' => $program->rentalId,
            'name' => $program->name,
            'room' => $program->room,
            'start_at' => $program->startAt->format('Y-m-d H:i:s'),
            'jingle_before' => $program->jingleBefore ? 1 : 0,
            'handheld_mic_count' => $program->handheldMicCount,
            'headset_mic_count' => $program->headsetMicCount,
            'current_delay_minutes' => $program->currentDelayMinutes,
            'created_at' => $program->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $program->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $program->name,
            'room2' => $program->room,
            'start_at2' => $program->startAt->format('Y-m-d H:i:s'),
            'jingle_before2' => $program->jingleBefore ? 1 : 0,
            'handheld_mic_count2' => $program->handheldMicCount,
            'headset_mic_count2' => $program->headsetMicCount,
            'current_delay_minutes2' => $program->currentDelayMinutes,
            'updated_at2' => $program->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?MinuteProgram
    {
        $stmt = $this->pdo->prepare('SELECT * FROM minute_programs WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM minute_programs WHERE rental_id = :rental_id ORDER BY start_at ASC');
        $stmt->execute(['rental_id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM minute_programs WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): MinuteProgram
    {
        return new MinuteProgram(
            id: $row['id'],
            rentalId: $row['rental_id'],
            name: $row['name'],
            room: $row['room'],
            startAt: new DateTimeImmutable($row['start_at']),
            jingleBefore: (bool) $row['jingle_before'],
            handheldMicCount: (int) $row['handheld_mic_count'],
            headsetMicCount: (int) $row['headset_mic_count'],
            currentDelayMinutes: (int) $row['current_delay_minutes'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
