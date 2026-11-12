<?php

declare(strict_types=1);

namespace App\Infrastructure\Csv;

use App\Application\Depreciation\DepreciationReport;

/**
 * Abschreibungsübersicht eines Jahres als CSV (eine Zeile je Inventarnummer).
 * Wie InsuranceListCsvExporter: Semikolon als Trennzeichen, deutsches
 * Dezimalkomma, UTF-8 mit BOM für Excel.
 */
final class DepreciationCsvExporter
{
    public function generate(DepreciationReport $report): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('CSV-Ausgabestream konnte nicht geöffnet werden.');
        }
        fwrite($handle, "\xEF\xBB\xBF");

        $year = $report->year;
        fputcsv($handle, [
            'Inventarnummer', 'Artikel', 'Kategorie', 'Angeschafft', 'Anschaffungskosten', 'Nutzungsdauer (Jahre)', 'GWG',
            'Buchwert 01.01.' . $year, 'Abschreibung ' . $year, 'Abgang ' . $year, 'Buchwert 31.12.' . $year, 'Verkauft am',
        ], ';');

        $money = static fn (float $value): string => number_format($value, 2, ',', '');
        foreach ($report->rows as $row) {
            $schedule = $row->schedule;
            fputcsv($handle, [
                $row->asset->number,
                $row->asset->label(),
                $row->asset->categoryName ?? '',
                $schedule->acquisitionDate->format('d.m.Y'),
                $money($schedule->cost),
                $schedule->usefulLifeYears,
                $schedule->lowValueAsset ? 'ja' : 'nein',
                $money($schedule->bookValueAtStartOf($year)),
                $money($schedule->amountIn($year)),
                $money($schedule->disposalIn($year)),
                $money($schedule->bookValueAtEndOf($year)),
                $schedule->disposalDate?->format('d.m.Y') ?? '',
            ], ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
