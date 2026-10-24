<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\CalDav;

use App\Domain\Rental\Rental;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Baut eine einzelne iCalendar-Ressource (RFC 5545, VCALENDAR mit genau
 * einem VEVENT) aus einer Vermietung — sowohl für den GET-Endpunkt einer
 * einzelnen Ressource als auch für die REPORT-Antworten (calendar-data),
 * siehe CalDavController.
 */
final class IcsEventWriter
{
    public static function uidFor(Rental $rental): string
    {
        return $rental->id . '@materialverleih';
    }

    /** ETag-Wert (inkl. umschließender Anführungszeichen, wie von RFC 4791 verlangt). */
    public static function etagFor(Rental $rental): string
    {
        return '"' . sha1($rental->id . '-' . $rental->updatedAt->format(DATE_ATOM)) . '"';
    }

    public static function write(Rental $rental): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Materialverleih//CalDAV//DE',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:' . self::uidFor($rental),
            'DTSTAMP:' . self::utcStamp(new DateTimeImmutable()),
            // Ganztägig (VALUE=DATE): DTEND ist exklusiv (RFC 5545) — daher
            // +1 Tag auf das (inklusive) Enddatum der Vermietung.
            'DTSTART;VALUE=DATE:' . $rental->startDate->format('Ymd'),
            'DTEND;VALUE=DATE:' . $rental->endDate->modify('+1 day')->format('Ymd'),
            'SUMMARY:' . self::escape($rental->eventName),
        ];

        if ($rental->eventLocation !== null && $rental->eventLocation !== '') {
            $lines[] = 'LOCATION:' . self::escape($rental->eventLocation);
        }

        $lines[] = 'LAST-MODIFIED:' . self::utcStamp($rental->updatedAt);
        $lines[] = 'SEQUENCE:0';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // RFC 5545 verlangt CRLF-Zeilenenden.
        return implode("\r\n", $lines) . "\r\n";
    }

    private static function utcStamp(DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /** Escaping nach RFC 5545 §3.3.11 (Kommas, Semikola, Backslashes, Zeilenumbrüche). */
    private static function escape(string $value): string
    {
        $value = str_replace(['\\', "\n", "\r", ',', ';'], ['\\\\', '\\n', '', '\\,', '\\;'], $value);

        return $value;
    }
}
