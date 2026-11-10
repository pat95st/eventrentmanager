<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\EventAudio\ScheduledPlayback;
use App\Domain\EventAudio\ScheduledPlaybackRepositoryInterface;
use App\Domain\EventAudio\ScheduledPlaybackStatus;
use DateTimeImmutable;
use PDO;

final class MariadbScheduledPlaybackRepository implements ScheduledPlaybackRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ScheduledPlayback $s): void
    {
        $this->pdo->prepare(
            'INSERT INTO scheduled_playbacks (id, rental_id, audio_file_id, play_at, note, status, playback_request_id, created_by_type, created_by_id, created_by_name, created_at, triggered_at, repeat_interval_minutes, repeat_until, repeat_max, repeat_count)
             VALUES (:id, :rental, :file, :play, :note, :status, :req, :btype, :bid, :bname, :created, :triggered, :rint, :runtil, :rmax, :rcount)
             ON DUPLICATE KEY UPDATE play_at = VALUES(play_at), note = VALUES(note), status = VALUES(status),
                playback_request_id = VALUES(playback_request_id), triggered_at = VALUES(triggered_at), repeat_count = VALUES(repeat_count)'
        )->execute([
            'id' => $s->id,
            'rental' => $s->rentalId,
            'file' => $s->audioFileId,
            'play' => $s->playAt->format('Y-m-d H:i:s'),
            'note' => $s->note,
            'status' => $s->status->value,
            'req' => $s->playbackRequestId,
            'btype' => $s->createdByType,
            'bid' => $s->createdById,
            'bname' => $s->createdByName,
            'created' => $s->createdAt->format('Y-m-d H:i:s'),
            'triggered' => $s->triggeredAt?->format('Y-m-d H:i:s'),
            'rint' => $s->repeatIntervalMinutes,
            'runtil' => $s->repeatUntil?->format('Y-m-d H:i:s'),
            'rmax' => $s->repeatMax,
            'rcount' => $s->repeatCount,
        ]);
    }

    public function findById(string $id): ?ScheduledPlayback
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scheduled_playbacks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findForRental(string $rentalId, int $recentLimit = 10): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM scheduled_playbacks WHERE rental_id = :id AND status = 'pending' ORDER BY play_at, created_at");
        $stmt->execute(['id' => $rentalId]);
        $pending = array_map(fn (array $r) => $this->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC));

        $stmt = $this->pdo->prepare("SELECT * FROM scheduled_playbacks WHERE rental_id = :id AND status <> 'pending' ORDER BY play_at DESC LIMIT " . max(0, $recentLimit));
        $stmt->execute(['id' => $rentalId]);

        return array_merge($pending, array_map(fn (array $r) => $this->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC)));
    }

    public function findDue(string $rentalId, DateTimeImmutable $now): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM scheduled_playbacks WHERE rental_id = :id AND status = 'pending' AND play_at <= :now ORDER BY play_at, created_at");
        $stmt->execute(['id' => $rentalId, 'now' => $now->format('Y-m-d H:i:s')]);

        return array_map(fn (array $r) => $this->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function claim(string $id, ScheduledPlaybackStatus $status, DateTimeImmutable $at): bool
    {
        $stmt = $this->pdo->prepare("UPDATE scheduled_playbacks SET status = :status, triggered_at = :at WHERE id = :id AND status = 'pending'");
        $stmt->execute(['status' => $status->value, 'at' => $at->format('Y-m-d H:i:s'), 'id' => $id]);

        return $stmt->rowCount() === 1;
    }

    public function attachRequest(string $id, string $playbackRequestId): void
    {
        $this->pdo->prepare('UPDATE scheduled_playbacks SET playback_request_id = :req WHERE id = :id')->execute(['req' => $playbackRequestId, 'id' => $id]);
    }

    public function advance(string $id, DateTimeImmutable $expectedPlayAt, ?DateTimeImmutable $next, int $repeatCount, ?string $playbackRequestId, DateTimeImmutable $at): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE scheduled_playbacks SET play_at = :next, repeat_count = :count, status = :status, triggered_at = :at,
                playback_request_id = COALESCE(:req, playback_request_id)
             WHERE id = :id AND status = 'pending' AND play_at = :expected"
        );
        $stmt->execute([
            'next' => ($next ?? $expectedPlayAt)->format('Y-m-d H:i:s'),
            'count' => $repeatCount,
            'status' => $next === null ? 'triggered' : 'pending',
            'at' => $at->format('Y-m-d H:i:s'),
            'req' => $playbackRequestId,
            'id' => $id,
            'expected' => $expectedPlayAt->format('Y-m-d H:i:s'),
        ]);

        return $stmt->rowCount() === 1;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ScheduledPlayback
    {
        return new ScheduledPlayback(
            id: $row['id'],
            rentalId: $row['rental_id'],
            audioFileId: $row['audio_file_id'],
            playAt: new DateTimeImmutable($row['play_at']),
            note: $row['note'],
            status: ScheduledPlaybackStatus::tryFrom((string) $row['status']) ?? ScheduledPlaybackStatus::Pending,
            playbackRequestId: $row['playback_request_id'],
            createdByType: $row['created_by_type'],
            createdById: $row['created_by_id'],
            createdByName: $row['created_by_name'],
            createdAt: new DateTimeImmutable($row['created_at']),
            triggeredAt: $row['triggered_at'] !== null ? new DateTimeImmutable($row['triggered_at']) : null,
            repeatIntervalMinutes: isset($row['repeat_interval_minutes']) ? (int) $row['repeat_interval_minutes'] : null,
            repeatUntil: isset($row['repeat_until']) ? new DateTimeImmutable($row['repeat_until']) : null,
            repeatMax: isset($row['repeat_max']) ? (int) $row['repeat_max'] : null,
            repeatCount: (int) ($row['repeat_count'] ?? 0),
        );
    }
}
