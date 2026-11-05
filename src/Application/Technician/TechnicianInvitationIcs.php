<?php

declare(strict_types=1);

namespace App\Application\Technician;

use App\Domain\Rental\Rental;
use App\Domain\Technician\RentalTechnician;
use App\Domain\Technician\RentalTechnicianStatus;
use App\Domain\User\User;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Termineinladung (iCalendar, METHOD:REQUEST) für einen Techniker
 * (Migration 134). Zeitraum: Abholung bis Rückgabe, falls beide gesetzt
 * sind, sonst ganztägig vom Start- bis zum Enddatum der Veranstaltung.
 * UID je Zuordnung, SEQUENCE steigt mit jeder Benachrichtigung, damit
 * Kalender Aktualisierungen übernehmen.
 */
final class TechnicianInvitationIcs
{
    public static function build(Rental $rental, RentalTechnician $assignment, User $technician, string $organizerEmail, string $organizerName, string $description, ?string $url = null): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Materialverleih//Techniker//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:' . $assignment->id . '@materialverleih-techniker',
            'DTSTAMP:' . self::utc(new DateTimeImmutable()),
            'SEQUENCE:' . ($assignment->reminderCount + ($assignment->lastNotifiedAt !== null ? 1 : 0)),
        ];
        if ($rental->pickupAt !== null && $rental->returnAt !== null && $rental->returnAt > $rental->pickupAt) {
            $lines[] = 'DTSTART:' . self::utc($rental->pickupAt);
            $lines[] = 'DTEND:' . self::utc($rental->returnAt);
        } else {
            $lines[] = 'DTSTART;VALUE=DATE:' . $rental->startDate->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $rental->endDate->modify('+1 day')->format('Ymd');
        }
        $lines[] = 'SUMMARY:' . self::escape($rental->eventName);
        if ($rental->eventLocation !== null && trim($rental->eventLocation) !== '') {
            $lines[] = 'LOCATION:' . self::escape($rental->eventLocation);
        }
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . self::escape($description);
        }
        if ($url !== null && $url !== '') {
            $lines[] = 'URL:' . $url;
        }
        if ($organizerEmail !== '') {
            $lines[] = 'ORGANIZER;CN=' . self::param($organizerName) . ':mailto:' . $organizerEmail;
        }
        $partStat = $assignment->status->isBooked() ? 'ACCEPTED' : ($assignment->status === RentalTechnicianStatus::Declined ? 'DECLINED' : 'NEEDS-ACTION');
        $lines[] = 'ATTENDEE;CN=' . self::param($technician->displayName()) . ';ROLE=REQ-PARTICIPANT;PARTSTAT=' . $partStat . ';RSVP=FALSE:mailto:' . $technician->email;
        $lines[] = 'STATUS:' . ($assignment->status->isBooked() ? 'CONFIRMED' : 'TENTATIVE');
        $lines[] = 'TRANSP:OPAQUE';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines)) . "\r\n";
    }

    private static function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', "\r", "\n", ',', ';'], ['\\\\', '', '\\n', '\\,', '\;'], $value);
    }

    private static function param(string $value): string
    {
        return '"' . str_replace(['"', "\r", "\n"], ["'", '', ' '], $value) . '"';
    }

    /** Zeilen über 75 Oktette nach RFC 5545 falten. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current . $char) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ") . $current;
                $current = '';
            }
            $current .= $char;
        }

        return $out . "\r\n " . $current;
    }
}
