<?php

declare(strict_types=1);

namespace App\Domain\Backup;

use DateTimeImmutable;

/** Log entry of one automatic backup run (table backup_runs). */
final class BackupRun
{
    /**
     * @param list<array{targetName: string, remotePath: string, success: bool, message: string}> $targetResults
     */
    public function __construct(
        public readonly string $id,
        public readonly BackupRunTrigger $trigger,
        public readonly BackupRunStatus $status,
        public readonly ?string $fileName,
        public readonly ?int $fileSize,
        // General error (e.g. archive could not be built), null otherwise.
        public readonly ?string $message,
        public readonly array $targetResults,
        public readonly DateTimeImmutable $startedAt,
        public readonly DateTimeImmutable $finishedAt,
    ) {
    }
}
