<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookEntry;
use DateTimeImmutable;

/**
 * Driver's logbook as PDF (migration 184, landscape A4): one row per trip in
 * driving order with date and times, driver, kind (event or purpose), start
 * and destination, odometer readings and kilometres; totals per kind at the
 * end. Used for "Fahrtenbuch je Fahrzeug und Zeitraum" from the logbook list
 * (any filter can be applied).
 */
final class LogbookPdfGenerator
{
    private const MARGIN = 36.0;
    private const FONT_SIZE = 8.0;
    private const LINE_HEIGHT = 10.0;
    private const ROW_PADDING = 5.0;

    /** Column => [title, relative width, right aligned]. */
    private const COLUMNS = [
        'date' => ['Datum / Zeit', 1.05, false],
        'driver' => ['Fahrer', 1.25, false],
        'kind' => ['Art / Zweck', 2.1, false],
        'route' => ['Start » Ziel', 3.0, false],
        'odoStart' => ['Km-Stand Abf.', 0.95, true],
        'odoEnd' => ['Km-Stand Ank.', 0.95, true],
        'km' => ['km', 0.6, true],
    ];

    /**
     * @param LogbookEntry[] $entries
     * @param array<string, string> $drivers entry id => driver name
     * @param array<string, string> $rentals entry id => event name
     * @param array<string, string> $vehicles vehicle id => "name (plate)"
     */
    public function generate(array $entries, array $drivers, array $rentals, array $vehicles, string $subtitle, ?DateTimeImmutable $from, ?DateTimeImmutable $to, string $companyName = ''): string
    {
        usort($entries, static fn (LogbookEntry $a, LogbookEntry $b) => [$a->tripDate, (string) $a->departTime, $a->odometerStart ?? 0, $a->createdAt] <=> [$b->tripDate, (string) $b->departTime, $b->odometerStart ?? 0, $b->createdAt]);

        $writer = new SimplePdfWriter(841.89, 595.28);
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();
        $columns = $this->columns($pageWidth);
        $page = 1;
        $period = $from !== null || $to !== null
            ? ($from?->format('d.m.Y') ?? '…') . ' – ' . ($to?->format('d.m.Y') ?? '…')
            : 'alle Fahrten';

        $writer->addPage();
        $y = $this->header($writer, $pageWidth, $pageHeight, $subtitle, $period, $companyName, $page, $columns);

        $totals = ['all' => 0];
        foreach ($entries as $entry) {
            $cells = $this->cells($writer, $entry, $drivers, $rentals, $vehicles, $columns);
            $lines = max(1, ...array_values(array_map('count', $cells)));
            $height = $lines * self::LINE_HEIGHT + self::ROW_PADDING;
            if ($y - $height < self::MARGIN + 30) {
                $page++;
                $writer->addPage();
                $y = $this->header($writer, $pageWidth, $pageHeight, $subtitle, $period, $companyName, $page, $columns);
            }
            foreach ($columns as $key => [$x, $width, $right]) {
                foreach ($cells[$key] as $i => $text) {
                    $lineY = $y - self::LINE_HEIGHT * ($i + 1) + 2;
                    $grey = $i > 0 ? '0.35 0.35 0.35' : '0 0 0';
                    $right
                        ? $writer->textRight($x + $width - 4, $lineY, $text, self::FONT_SIZE, false, false, $grey)
                        : $writer->text($x + 2, $lineY, $text, self::FONT_SIZE, false, false, $grey);
                }
            }
            $y -= $height;
            $writer->line(self::MARGIN, $y + 2, $pageWidth - self::MARGIN, $y + 2, 0.3);
            if (!$entry->isDeleted()) {
                $totals['all'] += $entry->distanceKm;
                $totals[$entry->category->value] = ($totals[$entry->category->value] ?? 0) + $entry->distanceKm;
            }
        }
        if ($entries === []) {
            $writer->text(self::MARGIN, $y - 14, 'Keine Fahrten im gewählten Zeitraum.', 10);
            $y -= 24;
        }

        if ($y < self::MARGIN + 70) {
            $page++;
            $writer->addPage();
            $y = $pageHeight - self::MARGIN;
        }
        $y -= 18;
        $count = count(array_filter($entries, static fn (LogbookEntry $e) => !$e->isDeleted()));
        $writer->text(self::MARGIN, $y, 'Summe: ' . number_format($totals['all'], 0, ',', '.') . ' km in ' . $count . ' ' . ($count === 1 ? 'Fahrt' : 'Fahrten'), 10, true);
        $parts = [];
        foreach (LogbookCategory::cases() as $category) {
            if (isset($totals[$category->value])) {
                $parts[] = $category->label() . ': ' . number_format($totals[$category->value], 0, ',', '.') . ' km';
            }
        }
        if ($parts !== []) {
            $writer->text(self::MARGIN, $y - 13, implode(' · ', $parts), 9);
        }

        return $writer->output();
    }

    /** @return array<string, array{0: float, 1: float, 2: bool}> column => [x, width, right aligned] */
    private function columns(float $pageWidth): array
    {
        $available = $pageWidth - 2 * self::MARGIN;
        $total = array_sum(array_column(self::COLUMNS, 1));
        $x = self::MARGIN;
        $result = [];
        foreach (self::COLUMNS as $key => [, $weight, $right]) {
            $width = $available * $weight / $total;
            $result[$key] = [$x, $width, $right];
            $x += $width;
        }

        return $result;
    }

    /** @param array<string, array{0: float, 1: float, 2: bool}> $columns */
    private function header(SimplePdfWriter $writer, float $pageWidth, float $pageHeight, string $subtitle, string $period, string $companyName, int $page, array $columns): float
    {
        $y = $pageHeight - self::MARGIN;
        $writer->textInRole(SimplePdfWriter::ROLE_HEADING, self::MARGIN, $y - 12, 'Fahrtenbuch', 16, true);
        $writer->textRight($pageWidth - self::MARGIN, $y - 10, ($companyName !== '' ? $companyName . ' · ' : '') . 'Seite ' . $page, 8, false, false, '0.4 0.4 0.4');
        $writer->text(self::MARGIN, $y - 28, $subtitle . ' · ' . $period, 10);
        $writer->textRight($pageWidth - self::MARGIN, $y - 28, 'erstellt am ' . (new DateTimeImmutable())->format('d.m.Y H:i'), 8, false, false, '0.4 0.4 0.4');
        $y -= 46;
        $writer->filledRect(self::MARGIN, $y - 14, $pageWidth - 2 * self::MARGIN, 16);
        foreach ($columns as $key => [$x, $width, $right]) {
            $title = self::COLUMNS[$key][0];
            $right ? $writer->textRight($x + $width - 4, $y - 9, $title, self::FONT_SIZE, true) : $writer->text($x + 2, $y - 9, $title, self::FONT_SIZE, true);
        }

        return $y - 16;
    }

    /**
     * @param array<string, string> $drivers
     * @param array<string, string> $rentals
     * @param array<string, string> $vehicles
     * @param array<string, array{0: float, 1: float, 2: bool}> $columns
     * @return array<string, string[]>
     */
    private function cells(SimplePdfWriter $writer, LogbookEntry $entry, array $drivers, array $rentals, array $vehicles, array $columns): array
    {
        $wrap = fn (string $text, string $key): array => $this->wrap($writer, $text, $columns[$key][1] - 6);
        $times = trim(($entry->departTime ?? '') . ' – ' . ($entry->arriveTime ?? ''), ' –');
        $kind = $entry->category->label() . (isset($rentals[$entry->id]) ? ': ' . $rentals[$entry->id] : '');
        $flags = array_filter([
            $entry->status->value !== 'approved' ? $entry->status->label() : null,
            $entry->notBilled ? 'nicht abgerechnet' : null,
            $entry->isDeleted() ? 'gelöscht' : null,
        ]);
        $route = $entry->from !== null || $entry->to !== null
            // "»" instead of an arrow: the PDF font has no arrow glyph.
            ? ($entry->from?->display() ?? '?') . ' » ' . ($entry->to?->display() ?? '?') . ($entry->roundTrip ? ' » zurück' : '')
            : '–';
        $vehicle = $entry->vehicleId !== null ? ($vehicles[$entry->vehicleId] ?? '') : (string) $entry->otherVehicleLabel;
        if ($entry->trailerId !== null) {
            $vehicle .= ' + ' . ($vehicles[$entry->trailerId] ?? 'Anhänger');
        }

        return [
            'date' => array_merge([$entry->tripDate->format('d.m.Y')], $times !== '' ? [$times] : []),
            'driver' => array_merge($wrap(($drivers[$entry->id] ?? '') . ($entry->isExternal ? ' (extern)' : ''), 'driver')),
            'kind' => array_merge($wrap($kind, 'kind'), $entry->purpose !== null ? $wrap($entry->purpose, 'kind') : [], $flags !== [] ? [implode(', ', $flags)] : []),
            'route' => array_merge($wrap($route, 'route'), $vehicle !== '' ? $wrap($vehicle, 'route') : []),
            'odoStart' => [$entry->odometerStart !== null ? number_format($entry->odometerStart, 0, ',', '.') : ''],
            'odoEnd' => [$entry->odometerEnd !== null ? number_format($entry->odometerEnd, 0, ',', '.') : ''],
            'km' => [number_format($entry->distanceKm, 0, ',', '.')],
        ];
    }

    /** @return string[] */
    private function wrap(SimplePdfWriter $writer, string $text, float $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && $writer->textWidth($candidate, self::FONT_SIZE) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines === [] ? [''] : $lines;
    }
}
