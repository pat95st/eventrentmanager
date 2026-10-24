<?php

declare(strict_types=1);

namespace App\Domain\Numbering;

use DateTimeImmutable;

/**
 * Reine Formatierungsfunktion (keine Seiteneffekte, keine Datenbankzugriffe)
 * — die eigentliche, race-condition-sichere Zählerstand-Vergabe übernimmt
 * App\Infrastructure\Persistence\Pdo\DocumentNumberSequence, die diese
 * Klasse nur zum Formatieren des fertig vergebenen Zählerstands nutzt.
 */
final class DocumentNumberFormatter
{
    public static function format(DocumentNumberingSettings $settings, int $year, int $number): string
    {
        $digits = str_pad((string) $number, $settings->digitCount, '0', STR_PAD_LEFT);
        $prefix = $settings->prefix ?? '';

        if ($settings->scheme === NumberingScheme::Simple) {
            return $prefix . $digits;
        }

        $parts = array_filter([$prefix !== '' ? $prefix : null, (string) $year, $digits], static fn ($part) => $part !== null && $part !== '');

        return implode($settings->separator, $parts);
    }

    /**
     * Schlüsseljahr für den Zählerstand (siehe document_number_sequences,
     * Primärschlüssel (document_type, sequence_year)): 0 beim Simple-Schema
     * (EIN nie zurückgesetzter Zähler), sonst das aktuelle Kalenderjahr —
     * der Jahreswechsel-Reset ergibt sich daraus einfach, dass für ein neues
     * Jahr noch keine Zeile existiert (siehe DocumentNumberSequence).
     */
    public static function previewYear(DocumentNumberingSettings $settings): int
    {
        if ($settings->scheme === NumberingScheme::Simple) {
            return 0;
        }

        return (int) (new DateTimeImmutable())->format('Y');
    }
}
