<?php

declare(strict_types=1);

namespace App\Application\Datev\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Datev\DatevExport;
use App\Domain\Datev\DatevExportRepositoryInterface;

final class ListDatevExportsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DatevExportRepositoryInterface $repository)
    {
    }

    /** @return DatevExport[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListDatevExports);

        return $this->repository->findRecent($query->limit);
    }
}
