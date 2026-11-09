<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\EventAudio\AudioFileKind;
use App\Domain\EventAudio\RentalAudioFile;
use App\Domain\EventAudio\RentalAudioFileRepositoryInterface;
use DateTimeImmutable;
use PDO;

/** Jingles/Audio-Dateien je Veranstaltung (Migration 135). */
final class MariadbRentalAudioFileRepository implements RentalAudioFileRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByRental(string $rentalId, ?AudioFileKind $kind = null): array
    {
        $sql = 'SELECT * FROM rental_audio_files WHERE rental_id = :rental';
        $params = ['rental' => $rentalId];
        if ($kind !== null) {
            $sql .= ' AND kind = :kind';
            $params['kind'] = $kind->value;
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY kind, sort_order, title');
        $stmt->execute($params);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?RentalAudioFile
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_audio_files WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(RentalAudioFile $file): void
    {
        $this->pdo->prepare(
            'INSERT INTO rental_audio_files (id, rental_id, kind, title, description, original_filename, stored_filename, mime_type, size_bytes, sort_order, created_at)
             VALUES (:id, :rental, :kind, :title, :description, :original, :stored, :mime, :size, :sort, :created)
             ON DUPLICATE KEY UPDATE kind = VALUES(kind), title = VALUES(title), description = VALUES(description), sort_order = VALUES(sort_order)'
        )->execute([
            'id' => $file->id,
            'rental' => $file->rentalId,
            'kind' => $file->kind->value,
            'title' => $file->title,
            'description' => $file->description,
            'original' => $file->originalFilename,
            'stored' => $file->storedFilename,
            'mime' => $file->mimeType,
            'size' => $file->sizeBytes,
            'sort' => $file->sortOrder,
            'created' => $file->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('UPDATE minute_programs SET jingle_audio_file_id = NULL WHERE jingle_audio_file_id = :id')->execute(['id' => $id]);
        $this->pdo->prepare('DELETE FROM rental_audio_files WHERE id = :id')->execute(['id' => $id]);
    }

    public function nextSortOrder(string $rentalId, AudioFileKind $kind): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM rental_audio_files WHERE rental_id = :rental AND kind = :kind');
        $stmt->execute(['rental' => $rentalId, 'kind' => $kind->value]);

        return (int) $stmt->fetchColumn();
    }

    private function hydrate(array $row): RentalAudioFile
    {
        return new RentalAudioFile(
            id: $row['id'],
            rentalId: $row['rental_id'],
            kind: AudioFileKind::tryFrom((string) $row['kind']) ?? AudioFileKind::Audio,
            title: $row['title'],
            description: $row['description'],
            originalFilename: $row['original_filename'],
            storedFilename: $row['stored_filename'],
            mimeType: $row['mime_type'],
            sizeBytes: (int) $row['size_bytes'],
            sortOrder: (int) $row['sort_order'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
