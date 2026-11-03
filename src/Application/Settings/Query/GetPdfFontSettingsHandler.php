<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\PdfFontRepositoryInterface;

final class GetPdfFontSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PdfFontRepositoryInterface $fonts)
    {
    }

    /** @return array{fonts: \App\Domain\Settings\PdfFont[], assignments: array<string, string>} */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof GetPdfFontSettings);

        return ['fonts' => $this->fonts->findAll(), 'assignments' => $this->fonts->assignments()];
    }
}
