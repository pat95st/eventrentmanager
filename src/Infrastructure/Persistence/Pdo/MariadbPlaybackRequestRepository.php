<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\EventAudio\PlaybackKind;
use App\Domain\EventAudio\PlaybackRequest;
use App\Domain\EventAudio\PlaybackRequestRepositoryInterface;
use App\Domain\EventAudio\PlaybackStatus;
use DateTimeImmutable;
use PDO;

final class MariadbPlaybackRequestRepository implements PlaybackRequestRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(PlaybackRequest $request): void
    {
        $fmt = static fn (?DateTimeImmutable $d): ?string => $d?->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO rental_playback_requests (id, rental_id, kind, audio_file_id, label, text, language, recording_stored_filename, recording_mime_type, with_gong,
                status, requested_by_type, requested_by_id, requested_by_name, player_id, error, created_at, started_at, finished_at)
             VALUES (:id, :rental, :kind, :file, :label, :text, :lang, :rec, :recmime, :gong, :status, :rtype, :rid, :rname, :player, :error, :created, :started, :finished)
             ON DUPLICATE KEY UPDATE status = VALUES(status), player_id = VALUES(player_id), error = VALUES(error),
                started_at = VALUES(started_at), finished_at = VALUES(finished_at)'
        )->execute([
            'id' => $request->id,
            'rental' => $request->rentalId,
            'kind' => $request->kind->value,
            'file' => $request->audioFileId,
            'label' => $request->label !== null ? mb_substr($request->label, 0, 160) : null,
            'lang' => $request->language,
            'text' => $request->text,
            'rec' => $request->recordingStoredFilename,
            'recmime' => $request->recordingMimeType,
            'gong' => $request->withGong ? 1 : 0,
            'status' => $request->status->value,
            'rtype' => $request->requestedByType,
            'rid' => $request->requestedById,
            'rname' => mb_substr($request->requestedByName, 0, 160),
            'player' => $request->playerId,
            'error' => $request->error !== null ? mb_substr($request->error, 0, 500) : null,
            'created' => $request->createdAt->format('Y-m-d H:i:s'),
            'started' => $fmt($request->startedAt),
            'finished' => $fmt($request->finishedAt),
        ]);
    }

    public function findById(string $id): ?PlaybackRequest
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_playback_requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function recentForRental(string $rentalId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_playback_requests WHERE rental_id = :id ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit));
        $stmt->execute(['id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function queuedForRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rental_playback_requests WHERE rental_id = :id AND status = 'queued' ORDER BY created_at ASC, id ASC");
        $stmt->execute(['id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function expireQueuedBefore(string $rentalId, DateTimeImmutable $before): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rental_playback_requests SET status = 'cancelled', error = 'Nicht rechtzeitig vom Abspielgerät abgeholt.', finished_at = NOW()
             WHERE rental_id = :id AND status = 'queued' AND created_at < :before"
        );
        $stmt->execute(['id' => $rentalId, 'before' => $before->format('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }

    private function hydrate(array $row): PlaybackRequest
    {
        $d = static fn ($v): ?DateTimeImmutable => $v !== null ? new DateTimeImmutable($v) : null;

        return new PlaybackRequest(
            id: $row['id'],
            rentalId: $row['rental_id'],
            kind: PlaybackKind::tryFrom((string) $row['kind']) ?? PlaybackKind::Audio,
            audioFileId: $row['audio_file_id'],
            text: $row['text'],
            recordingStoredFilename: $row['recording_stored_filename'],
            recordingMimeType: $row['recording_mime_type'],
            withGong: (bool) $row['with_gong'],
            status: PlaybackStatus::tryFrom((string) $row['status']) ?? PlaybackStatus::Failed,
            requestedByType: $row['requested_by_type'],
            requestedById: $row['requested_by_id'],
            requestedByName: $row['requested_by_name'],
            playerId: $row['player_id'],
            error: $row['error'],
            createdAt: new DateTimeImmutable($row['created_at']),
            startedAt: $d($row['started_at']),
            finishedAt: $d($row['finished_at']),
            label: $row['label'],
            language: $row['language'] ?? null,
        );
    }
}
