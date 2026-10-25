<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\PdfLayout\PdfLayoutSettings;
use App\Domain\Settings\PdfLayout\PdfLayoutSettingsRepositoryInterface;

final class GetPdfLayoutSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PdfLayoutSettingsRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): PdfLayoutSettings
    {
        assert($query instanceof GetPdfLayoutSettings);

        return $this->repository->getForDocumentType($query->documentType);
    }
}
