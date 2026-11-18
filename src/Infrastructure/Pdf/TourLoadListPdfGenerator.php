<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\Tour\Query\TourOverview;

/**
 * Load lists of a tour (migration 177): what is loaded at the start, and per
 * stop the material needed there plus what has to be loaded/unloaded
 * compared to the previous stop.
 */
final class TourLoadListPdfGenerator
{
    private const MARGIN = 50.0;
    private const LINE = 14.0;

    private SimplePdfWriter $writer;
    private float $y = 0.0;

    public function generate(TourOverview $tour): string
    {
        $this->writer = new SimplePdfWriter();
        $this->newPage();
        $this->writer->textInRole(SimplePdfWriter::ROLE_HEADING, self::MARGIN, $this->y, 'Ladelisten der Tour', 18, true);
        $this->y -= 22;
        $this->writer->text(self::MARGIN, $this->y, $tour->rental->eventName . ' · ' . $tour->rental->startDate->format('d.m.Y') . ' – ' . $tour->rental->endDate->format('d.m.Y'), 10);
        $this->y -= 26;

        $this->heading('Beladung zum Tourstart');
        $this->materialRows($tour, $tour->startLoad);

        foreach ($tour->entries as $entry) {
            $stop = $entry->stop;
            if (!$stop->isStop()) {
                $this->ensureSpace(3);
                $time = $stop->arriveAt?->format('d.m.Y H:i') ?? '';
                $this->writer->text(self::MARGIN, $this->y, '→ ' . $stop->title() . ($time !== '' ? ' (' . $time . ')' : ''), 10, false, true, '0.35 0.35 0.35');
                $this->y -= self::LINE + 4;
                continue;
            }
            $when = $stop->eventStartAt?->format('d.m.Y H:i') ?? '';
            $this->heading('Stop ' . $entry->number . ': ' . $stop->title() . ($when !== '' ? ' – ' . $when : ''));
            if ($entry->changes !== []) {
                $this->writer->text(self::MARGIN, $this->y, 'Änderungen gegenüber ' . ($entry->number === 1 ? 'der Grundliste' : 'dem vorherigen Stop') . ':', 10, true);
                $this->y -= self::LINE;
                foreach ($entry->changes as $change) {
                    $this->ensureSpace(1);
                    $diff = $change['after'] - $change['before'];
                    $verb = $diff > 0 ? 'zusätzlich laden' : 'abladen';
                    $this->writer->text(self::MARGIN + 10, $this->y, sprintf('%s%d× %s (%s)', $diff > 0 ? '+' : '−', abs($diff), $tour->articleName($change['articleId']), $verb), 10, false, false, $diff > 0 ? '0 0.4 0' : '0.6 0 0');
                    $this->y -= self::LINE;
                }
                $this->y -= 4;
            }
            $this->writer->text(self::MARGIN, $this->y, 'Benötigt an diesem Stop:', 10, true);
            $this->y -= self::LINE;
            $this->materialRows($tour, $entry->material);
        }

        return $this->writer->output();
    }

    /** @param array<string, int> $material */
    private function materialRows(TourOverview $tour, array $material): void
    {
        if ($material === []) {
            $this->writer->text(self::MARGIN + 10, $this->y, 'Kein Material.', 10);
            $this->y -= self::LINE;
        }
        $rows = [];
        foreach ($material as $articleId => $quantity) {
            $rows[$tour->articleName((string) $articleId)] = $quantity;
        }
        ksort($rows, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($rows as $name => $quantity) {
            $this->ensureSpace(1);
            $this->writer->drawCircle(self::MARGIN + 13, $this->y + 3.5, 4);
            $this->writer->textRight(self::MARGIN + 50, $this->y, $quantity . '×', 10);
            $this->writer->text(self::MARGIN + 58, $this->y, mb_strimwidth((string) $name, 0, 90, '…'), 10);
            $this->y -= self::LINE;
        }
        $this->y -= 10;
    }

    private function heading(string $text): void
    {
        $this->ensureSpace(4);
        $this->writer->filledRect(self::MARGIN - 4, $this->y - 4, $this->writer->pageWidth() - 2 * self::MARGIN + 8, 18);
        $this->writer->text(self::MARGIN, $this->y, mb_strimwidth($text, 0, 95, '…'), 11, true);
        $this->y -= self::LINE + 8;
    }

    private function ensureSpace(int $lines): void
    {
        if ($this->y - $lines * self::LINE < self::MARGIN) {
            $this->newPage();
        }
    }

    private function newPage(): void
    {
        $this->writer->addPage();
        $this->y = $this->writer->pageHeight() - self::MARGIN;
    }
}
