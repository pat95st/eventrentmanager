<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryInterface;
use App\Domain\Settings\PdfLayout\PdfDocumentType;

final class GetPdfLayoutSettings implements QueryInterface
{
    public function __construct(public readonly PdfDocumentType $documentType)
    {
    }
}
