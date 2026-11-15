<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Backup\BackupRun;
use App\Domain\Backup\BackupRunRepositoryInterface;
use App\Domain\Backup\BackupRunStatus;
use App\Domain\Backup\BackupRunTrigger;
use DateTimeImmutable;
use PDO;

final class MariadbBackupRunRepository implements BackupRunRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(BackupRun $run): void
    {
        $this->pdo->prepare(
            'INSERT INTO backup_runs (id, run_trigger, status, file_name, file_size, message, details, started_at, finished_at)
             VALUES (:id, :run_trigger, :status, :file_name, :file_size, :message, :details, :started_at, :finished_at)',
        )->execute([
            'id' => $run->id,
            'run_trigger' => $run->trigger->value,
            'status' => $run->status->value,
            'file_name' => $run->fileName,
            'file_size' => $run->fileSize,
            'message' => $run->message,
            'details' => json_encode($run->targetResults, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'started_at' => $run->startedAt->format('Y-m-d H:i:s'),
            'finished_at' => $run->finishedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findRecent(int $limit): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM backup_runs ORDER BY started_at DESC LIMIT :limit');
        $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        $runs = [];
        foreach ($statement->fetchAll() as $row) {
            $details = json_decode((string) ($row['details'] ?? '[]'), true);
            $results = [];
            foreach (is_array($details) ? $details : [] as $result) {
                if (!is_array($result)) {
                    continue;
                }
                $results[] = [
                    'targetName' => (string) ($result['targetName'] ?? ''),
                    'remotePath' => (string) ($result['remotePath'] ?? ''),
                    'success' => (bool) ($result['success'] ?? false),
                    'message' => (string) ($result['message'] ?? ''),
                ];
            }

            $runs[] = new BackupRun(
                id: (string) $row['id'],
                trigger: BackupRunTrigger::tryFrom((string) $row['run_trigger']) ?? BackupRunTrigger::Cron,
                status: BackupRunStatus::tryFrom((string) $row['status']) ?? BackupRunStatus::Failed,
                fileName: $row['file_name'] !== null ? (string) $row['file_name'] : null,
                fileSize: $row['file_size'] !== null ? (int) $row['file_size'] : null,
                message: $row['message'] !== null ? (string) $row['message'] : null,
                targetResults: $results,
                startedAt: new DateTimeImmutable((string) $row['started_at']),
                finishedAt: new DateTimeImmutable((string) $row['finished_at']),
            );
        }

        return $runs;
    }
}
