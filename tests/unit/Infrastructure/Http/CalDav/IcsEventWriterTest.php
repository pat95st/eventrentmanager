<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\CalDav;

use App\Domain\Rental\Rental;
use App\Infrastructure\Http\CalDav\IcsEventWriter;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class IcsEventWriterTest extends Unit
{
    public function testWritesAWellFormedVEventWithTheExpectedFields(): void
    {
        $rental = $this->makeRental(
            eventName: 'Sommerfest',
            eventLocation: 'Stadthalle',
            startDate: new DateTimeImmutable('2026-07-10'),
            endDate: new DateTimeImmutable('2026-07-12'),
        );

        $ics = IcsEventWriter::write($rental);

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("UID:" . IcsEventWriter::uidFor($rental) . "\r\n", $ics);
        $this->assertStringContainsString("SUMMARY:Sommerfest\r\n", $ics);
        $this->assertStringContainsString("LOCATION:Stadthalle\r\n", $ics);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20260710\r\n", $ics);
        // DTEND ist exklusiv (RFC 5545) — daher +1 Tag auf das inklusive Enddatum.
        $this->assertStringContainsString("DTEND;VALUE=DATE:20260713\r\n", $ics);
    }

    public function testOmitsTheLocationLineWhenNoLocationIsSet(): void
    {
        $rental = $this->makeRental(
            eventName: 'Ohne Ort',
            eventLocation: null,
            startDate: new DateTimeImmutable('2026-07-10'),
            endDate: new DateTimeImmutable('2026-07-10'),
        );

        $ics = IcsEventWriter::write($rental);

        $this->assertStringNotContainsString('LOCATION:', $ics);
    }

    public function testEscapesCommasSemicolonsAndBackslashesInTheSummaryPerRfc5545(): void
    {
        $rental = $this->makeRental(
            eventName: 'Firma A, B; Test\\Event',
            eventLocation: null,
            startDate: new DateTimeImmutable('2026-07-10'),
            endDate: new DateTimeImmutable('2026-07-10'),
        );

        $ics = IcsEventWriter::write($rental);

        $this->assertStringContainsString('SUMMARY:Firma A\\, B\\; Test\\\\Event' . "\r\n", $ics);
    }

    public function testEtagChangesWhenTheRentalIsUpdated(): void
    {
        $rental = $this->makeRental(
            eventName: 'Test',
            eventLocation: null,
            startDate: new DateTimeImmutable('2026-07-10'),
            endDate: new DateTimeImmutable('2026-07-10'),
        );

        $originalEtag = IcsEventWriter::etagFor($rental);

        $rental->updatedAt = $rental->updatedAt->modify('+1 minute');

        $this->assertNotSame($originalEtag, IcsEventWriter::etagFor($rental));
        // ETags sind laut RFC 4791 in Anführungszeichen einzuschließen.
        $this->assertStringStartsWith('"', IcsEventWriter::etagFor($rental));
        $this->assertStringEndsWith('"', IcsEventWriter::etagFor($rental));
    }

    private function makeRental(string $eventName, ?string $eventLocation, DateTimeImmutable $startDate, DateTimeImmutable $endDate): Rental
    {
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        return new Rental(
            id: 'rental-abc',
            customerId: 'customer-1',
            startDate: $startDate,
            endDate: $endDate,
            eventName: $eventName,
            eventLocation: $eventLocation,
            venueId: null,
            deploymentDays: 1,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
