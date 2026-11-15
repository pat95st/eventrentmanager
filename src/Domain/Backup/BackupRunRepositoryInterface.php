<?php

declare(strict_types=1);

namespace App\Domain\Backup;

interface BackupRunRepositoryInterface
{
    public function save(BackupRun $run): void;

    /** @return list<BackupRun> Newest first. */
    public function findRecent(int $limit): array;
}
