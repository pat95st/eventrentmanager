<?php

declare(strict_types=1);

namespace App\Application\Printing\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Printing\LabelPrinterRepositoryInterface;

final class ListLabelPrintersHandler implements QueryHandlerInterface
{
    public function __construct(private readonly LabelPrinterRepositoryInterface $printers)
    {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListLabelPrinters);

        return $this->printers->findAll();
    }
}
