<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Backup\BackupRun;
use App\Domain\Backup\BackupRunRepositoryInterface;

/** In-memory test double for BackupRunRepositoryInterface (see tests/README.md). */
final class InMemoryBackupRunRepository implements BackupRunRepositoryInterface
{
    /** @var list<BackupRun> */
    public array $runs = [];

    public function save(BackupRun $run): void
    {
        $this->runs[] = $run;
    }

    public function findRecent(int $limit): array
    {
        return array_slice(array_reverse($this->runs), 0, $limit);
    }
}
