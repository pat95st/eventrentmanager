<?php

declare(strict_types=1);

namespace App\Application\Backup\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Backup\BackupRun;
use App\Domain\Backup\BackupRunRepositoryInterface;

final class ListBackupRunsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly BackupRunRepositoryInterface $repository)
    {
    }

    /** @return list<BackupRun> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListBackupRuns);

        return $this->repository->findRecent($query->limit);
    }
}
