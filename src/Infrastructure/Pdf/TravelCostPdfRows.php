<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\TravelCost\TravelCostSection;

/**
 * Table rows of the travel cost section (migration 182) for the offer,
 * order confirmation and invoice PDFs, in the same four-column grid as the
 * labor time table: trip (with date and an optional second line such as the
 * route), kilometres, price per km, total.
 */
final class TravelCostPdfRows
{
    public const TITLE = 'Fahrtkosten';
    public const LABELS = ['Fahrt', 'km', 'Preis/km', 'Summe'];

    /**
     * @param callable(string, float, float): string[] $wrap
     * @param callable(float): string $amount
     * @return array<int, array{nameLines: string[], shortTextLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}>
     */
    public static function build(TravelCostSection $section, float $columnWidth, float $fontSize, callable $wrap, callable $amount): array
    {
        return self::rows($section, $columnWidth, $fontSize, $wrap, $amount);
    }

    /**
     * Note below planned travel costs (migration 187), copied into the
     * section when it was taken over. Offers and order confirmations only.
     */
    public static function estimateNote(TravelCostSection $section): ?string
    {
        $note = trim((string) $section->estimateNote);

        return $note !== '' && $section->basis->isPlanned() ? $note : null;
    }

    /**
     * Row with the note (no amounts) to append after the travel cost rows.
     *
     * @param callable(string, float, float): string[] $wrap
     * @return array{nameLines: string[], shortTextLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}
     */
    public static function noteRow(string $note, float $columnWidth, callable $wrap): array
    {
        return ['nameLines' => [], 'shortTextLines' => $wrap($note, $columnWidth, ArticlePdfRow::SHORT_TEXT_FONT_SIZE), 'quantity' => '', 'unitPrice' => '', 'lineTotal' => '', 'isSection' => false];
    }

    /**
     * @param callable(string, float, float): string[] $wrap
     * @param callable(float): string $amount
     * @return array<int, array{nameLines: string[], shortTextLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}>
     */
    private static function rows(TravelCostSection $section, float $columnWidth, float $fontSize, callable $wrap, callable $amount): array
    {
        $rows = [];
        foreach ($section->lines as $line) {
            $km = (float) $line->kilometers;
            $name = ($line->tripDate !== null ? $line->tripDate->format('d.m.Y') . ' · ' : '') . $line->description;
            $rows[] = [
                'nameLines' => $wrap($name, $columnWidth, $fontSize),
                'shortTextLines' => $line->detail !== null && $line->detail !== '' ? $wrap($line->detail, $columnWidth, ArticlePdfRow::SHORT_TEXT_FONT_SIZE) : [],
                'quantity' => number_format($km, $km == floor($km) ? 0 : 2, ',', '.'),
                'unitPrice' => $amount((float) $line->ratePerKm),
                'lineTotal' => $amount($line->lineTotal()),
                'isSection' => false,
            ];
        }

        return $rows;
    }
}
