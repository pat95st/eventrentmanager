<?php

declare(strict_types=1);

namespace App\Application\Backup\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Backup\BackupSettings;
use App\Domain\Backup\BackupSettingsRepositoryInterface;

final class GetBackupSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly BackupSettingsRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): BackupSettings
    {
        assert($query instanceof GetBackupSettings);

        return $this->repository->get();
    }
}
