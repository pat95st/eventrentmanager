<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Datev;

use App\Domain\Datev\DatevBooking;
use App\Domain\Datev\DatevSettings;
use App\Infrastructure\Datev\DatevBuchungsstapelWriter;
use Codeception\Test\Unit;
use DateTimeImmutable;
use InvalidArgumentException;

final class DatevBuchungsstapelWriterTest extends Unit
{
    private function settings(): DatevSettings
    {
        return new DatevSettings(enabled: true, consultantNumber: 29098, clientNumber: 55003);
    }

    /** @return list<list<string>> */
    private function parse(string $content): array
    {
        $utf8 = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        $lines = explode("\r\n", rtrim($utf8, "\r\n"));

        return array_map(static fn (string $line) => str_getcsv($line, ';', '"', ''), $lines);
    }

    public function testWritesHeaderColumnLineAndBookings(): void
    {
        $booking = DatevBooking::signed(
            signedAmount: 1190.0,
            account: 10000,
            contraAccount: 8400,
            taxKey: null,
            documentDate: new DateTimeImmutable('2026-10-05'),
            documentField1: 'RE-2026-0001',
            documentField2: null,
            text: 'Rechnung RE-2026-0001 Müller "Events"',
            serviceDate: new DateTimeImmutable('2026-10-03'),
            documentInfo: ['Kunde' => 'Müller Events'],
        );

        $content = (new DatevBuchungsstapelWriter())->write(
            $this->settings(),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-10-31'),
            'Verleih 10/2026',
            [$booking],
            new DateTimeImmutable('2026-11-12 08:30:00'),
        );

        $this->assertStringEndsWith("\r\n", $content);
        $this->assertFalse(mb_check_encoding($content, 'UTF-8') && preg_match('/\xC3\xBC/', $content) === 1, 'must be Windows-1252, not UTF-8');

        $rows = $this->parse($content);
        $this->assertCount(3, $rows);

        [$header, $columns, $row] = $rows;
        $this->assertSame(['EXTF', '700', '21', 'Buchungsstapel', '13'], array_slice($header, 0, 5));
        $this->assertSame('20261112083000000', $header[5]);
        $this->assertSame('29098', $header[10]);
        $this->assertSame('55003', $header[11]);
        $this->assertSame('20260101', $header[12]);
        $this->assertSame('4', $header[13]);
        $this->assertSame(['20261001', '20261031'], [$header[14], $header[15]]);
        $this->assertSame('03', $header[26]);
        $this->assertCount(31, $header);

        $this->assertCount(125, $columns);
        $this->assertCount(125, $row);
        $this->assertSame('1190,00', $row[0]);
        $this->assertSame('S', $row[1]);
        $this->assertSame('10000', $row[6]);
        $this->assertSame('8400', $row[7]);
        $this->assertSame('0510', $row[9]);
        $this->assertSame('RE-2026-0001', $row[10]);
        $this->assertSame('Rechnung RE-2026-0001 Müller "Events"', $row[13]);
        $this->assertSame(['Kunde', 'Müller Events'], [$row[20], $row[21]]);
        $this->assertSame('03102026', $row[114]);
    }

    public function testNegativeAmountsBecomeCreditBookings(): void
    {
        $booking = DatevBooking::signed(-59.5, 10000, 8400, null, new DateTimeImmutable('2026-10-20'), 'RK-0001', null, 'Stornorechnung');

        $this->assertFalse($booking->isDebit);
        $this->assertSame(59.5, $booking->amount);
    }

    public function testSanitisesDocumentField(): void
    {
        $this->assertSame('RE-2026-0001', DatevBuchungsstapelWriter::documentField('RE 2026_0001'));
        $this->assertSame(36, strlen(DatevBuchungsstapelWriter::documentField(str_repeat('A', 50))));
    }

    public function testRejectsBatchesCrossingTheFiscalYear(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DatevBuchungsstapelWriter())->write(
            $this->settings(),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-12-01'),
            new DateTimeImmutable('2027-01-31'),
            'x',
            [],
        );
    }
}
