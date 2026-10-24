<?php

declare(strict_types=1);

namespace App\Application\Numbering\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Numbering\DocumentNumberFormatter;
use App\Domain\Numbering\DocumentNumberingSettingsRepositoryInterface;

final class ListDocumentNumberingSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DocumentNumberingSettingsRepositoryInterface $numberingSettings)
    {
    }

    /** @return NumberingSettingsOverviewEntry[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListDocumentNumberingSettings);

        $result = [];
        foreach ($this->numberingSettings->findAll() as $settings) {
            $year = DocumentNumberFormatter::previewYear($settings);
            $nextNumber = $this->numberingSettings->getNextNumber($settings->documentType, $year);
            $preview = DocumentNumberFormatter::format($settings, $year, $nextNumber);

            $result[] = new NumberingSettingsOverviewEntry(
                settings: $settings,
                previewYear: $year,
                nextNumber: $nextNumber,
                previewFormatted: $preview,
            );
        }

        return $result;
    }
}
