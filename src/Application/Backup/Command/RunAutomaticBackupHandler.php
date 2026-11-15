<?php

declare(strict_types=1);

namespace App\Application\Backup\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Backup\BackupRun;
use App\Domain\Backup\BackupRunRepositoryInterface;
use App\Domain\Backup\BackupRunStatus;
use App\Domain\Backup\BackupSettingsRepositoryInterface;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Infrastructure\Backup\BackupArchiveBuilder;
use App\Infrastructure\DocumentStorage\DocumentStoragePathResolver;
use App\Infrastructure\DocumentStorage\StorageClientFactoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;
use Throwable;

/**
 * Builds the backup ZIP (BackupArchiveBuilder, same content as the manual
 * download) and uploads it to every selected, enabled storage target. A
 * failing target does not prevent the upload to the other targets. Every
 * run — successful or not — is logged in backup_runs and returned to the
 * caller; this handler never throws for upload/build errors.
 *
 * The ZIP is read into memory once, because StorageClientInterface::upload()
 * takes the contents as a string (the cron script lifts PHP's memory limit).
 */
final class RunAutomaticBackupHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly BackupSettingsRepositoryInterface $settings,
        private readonly DocumentStorageTargetRepositoryInterface $targets,
        private readonly StorageClientFactoryInterface $clientFactory,
        private readonly BackupArchiveBuilder $archiveBuilder,
        private readonly BackupRunRepositoryInterface $runs,
    ) {
    }

    public function handle(CommandInterface $command): BackupRun
    {
        assert($command instanceof RunAutomaticBackup);

        $startedAt = new DateTimeImmutable();
        $settings = $this->settings->get();

        $targets = [];
        foreach ($settings->storageTargetIds as $id) {
            $target = $this->targets->findById($id);
            if ($target !== null && $target->enabled) {
                $targets[] = $target;
            }
        }

        if ($targets === []) {
            return $this->record($command, BackupRunStatus::Failed, null, null, 'Kein aktives Speicherziel für die Sicherung ausgewählt.', [], $startedAt);
        }

        try {
            $archive = $this->archiveBuilder->build();
        } catch (Throwable $e) {
            return $this->record($command, BackupRunStatus::Failed, null, null, 'Die Sicherung konnte nicht erstellt werden: ' . $e->getMessage(), [], $startedAt);
        }

        $fileName = $archive['downloadName'];
        $results = [];

        try {
            $contents = file_get_contents($archive['absolutePath']);
            if ($contents === false) {
                return $this->record($command, BackupRunStatus::Failed, $fileName, null, 'Die erstellte Sicherungs-ZIP konnte nicht gelesen werden.', [], $startedAt);
            }
            $fileSize = strlen($contents);

            $remotePath = DocumentStoragePathResolver::resolvePathTemplate($settings->targetPath, [
                '{year}' => $startedAt->format('Y'),
                '{month}' => $startedAt->format('m'),
                '{day}' => $startedAt->format('d'),
                '{filename}' => $fileName,
            ]);

            foreach ($targets as $target) {
                try {
                    $this->clientFactory->create($target)->upload($contents, $remotePath);
                    $results[] = ['targetName' => $target->name, 'remotePath' => $remotePath, 'success' => true, 'message' => 'Erfolgreich übertragen.'];
                } catch (Throwable $e) {
                    $results[] = ['targetName' => $target->name, 'remotePath' => $remotePath, 'success' => false, 'message' => mb_substr($e->getMessage(), 0, 1000)];
                }
            }
            unset($contents);
        } finally {
            @unlink($archive['absolutePath']);
        }

        $successCount = count(array_filter($results, static fn (array $r): bool => $r['success']));
        $status = match (true) {
            $successCount === count($results) => BackupRunStatus::Success,
            $successCount > 0 => BackupRunStatus::Partial,
            default => BackupRunStatus::Failed,
        };

        return $this->record($command, $status, $fileName, $fileSize, null, $results, $startedAt);
    }

    /** @param list<array{targetName: string, remotePath: string, success: bool, message: string}> $results */
    private function record(
        RunAutomaticBackup $command,
        BackupRunStatus $status,
        ?string $fileName,
        ?int $fileSize,
        ?string $message,
        array $results,
        DateTimeImmutable $startedAt,
    ): BackupRun {
        $run = new BackupRun(
            id: Uuid::v4(),
            trigger: $command->trigger,
            status: $status,
            fileName: $fileName,
            fileSize: $fileSize,
            message: $message !== null ? mb_substr($message, 0, 1000) : null,
            targetResults: $results,
            startedAt: $startedAt,
            finishedAt: new DateTimeImmutable(),
        );

        try {
            $this->runs->save($run);
        } catch (Throwable $e) {
            // Logging must not hide the actual result from the caller.
            error_log('RunAutomaticBackupHandler: backup run could not be logged: ' . $e->getMessage());
        }

        return $run;
    }
}
