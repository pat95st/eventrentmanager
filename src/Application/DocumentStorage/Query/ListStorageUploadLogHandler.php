<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\DocumentStorage\DocumentStorageUploadLogRepositoryInterface;

final class ListStorageUploadLogHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DocumentStorageUploadLogRepositoryInterface $log)
    {
    }

    public function handle(QueryInterface $query): mixed
    {
        assert($query instanceof ListStorageUploadLog);

        return $this->log->findRecent($query->limit);
    }
}
