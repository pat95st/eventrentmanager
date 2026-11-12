<?php

declare(strict_types=1);

namespace App\Infrastructure\Csv;

use App\Application\Insurance\Query\RentalInsuranceList;
use App\Domain\Insurance\InsuranceField;

/**
 * Versicherungsliste einer Veranstaltung als CSV (eine Zeile je Exemplar,
 * nur die gewählten Spalten). Wie PriceListCsvExporter: Semikolon als
 * Trennzeichen, deutsches Dezimalkomma, UTF-8 mit BOM für Excel. Fehlende
 * Werte bleiben leer.
 */
final class InsuranceListCsvExporter
{
    /** @param list<InsuranceField> $fields */
    public function generate(RentalInsuranceList $list, array $fields): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('CSV-Ausgabestream konnte nicht geöffnet werden.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map(static fn (InsuranceField $f) => $f->label(), $fields), ';');

        foreach ($list->rows as $row) {
            $line = [];
            foreach ($fields as $field) {
                $value = $row->value($field);
                if ($value !== null && $field->isAmount()) {
                    $value = number_format((float) $value, 2, ',', '');
                }
                $line[] = self::neutralize($value ?? '');
            }
            fputcsv($handle, $line, ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /** Verhindert, dass Tabellenprogramme Freitext (z. B. Artikelnamen) als Formel ausführen. */
    private static function neutralize(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value)
            ? "'" . $value
            : $value;
    }
}
