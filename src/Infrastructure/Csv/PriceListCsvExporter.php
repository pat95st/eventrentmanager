<?php

declare(strict_types=1);

namespace App\Infrastructure\Csv;

use App\Application\Article\Query\ArticleListItem;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Settings\PriceRoundingDirection;
use App\Domain\Settings\PriceRoundingStep;
use App\Domain\Settings\PriceTier;
use App\Domain\Settings\RentalPriceCalculator;

/**
 * Erzeugt dieselbe Artikel × Einsatztage-Preismatrix wie PriceListPdfGenerator,
 * aber als CSV zur Weiterverarbeitung (z. B. in Excel). Semikolon als
 * Trennzeichen und deutsches Komma als Dezimaltrennzeichen, da die Software
 * primär für den deutschsprachigen Raum ausgelegt ist (siehe auch die
 * restliche Zahlenformatierung, z. B. RentalPriceCalculator-Aufrufer in den
 * Views).
 */
final class PriceListCsvExporter
{
    /**
     * @param ArticleListItem[] $articles nur abrechenbare Artikel (isBillable), bereits alphabetisch sortiert, mit aufgelöstem Hersteller
     * @param PriceTier[]       $tiers
     */
    public function generate(
        array $articles,
        array $tiers,
        PriceRoundingStep $roundingStep = PriceRoundingStep::None,
        PriceRoundingDirection $roundingDirection = PriceRoundingDirection::Nearest,
    ): string {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('CSV-Ausgabestream konnte nicht geöffnet werden.');
        }

        // BOM, damit Excel unter Windows die UTF-8-Kodierung (Umlaute) korrekt erkennt.
        fwrite($handle, "\xEF\xBB\xBF");

        $header = ['Artikel'];
        foreach ($tiers as $tier) {
            $header[] = $tier->dayCount . ($tier->dayCount === 1 ? ' Tag' : ' Tage');
        }
        fputcsv($handle, $header, ';');

        foreach ($articles as $item) {
            $row = [ArticleDisplayName::of($item->article, $item->manufacturer)];

            if ($item->article->dailyRentalPrice === null) {
                foreach ($tiers as $tier) {
                    $row[] = '';
                }
            } else {
                $prices = RentalPriceCalculator::calculate($item->article->dailyRentalPrice, $tiers, $roundingStep, $roundingDirection);
                foreach ($tiers as $tier) {
                    $price = $prices[$tier->dayCount] ?? null;
                    $row[] = $price !== null ? str_replace('.', ',', $price) : '';
                }
            }

            fputcsv($handle, $row, ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
