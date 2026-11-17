<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Category\Category;
use App\Domain\Manufacturer\Manufacturer;

/**
 * Builds the prompt for AI ad images (see GenerateArticleAdImageHandler).
 *
 * By default the image must not contain any text. Optionally the article's
 * display name, a short list of key properties (manufacturer, category,
 * weight, dimensions, rack data) and/or the daily rental price are rendered
 * into the image as a text overlay. The price is shown as a starting price
 * ("ab …") by default.
 *
 * With reference images (gallery photos of the article) the model is told to
 * reproduce the product exactly as shown; with several photos they are
 * described as views of the same product from different angles.
 */
final class ArticleAdImagePromptBuilder
{
    public function build(
        Article $article,
        ?Manufacturer $manufacturer,
        ?Category $category,
        bool $fromReferenceImage,
        bool $includeArticleName,
        bool $includeProperties,
        ?string $additionalInstructions,
        bool $includePrice = false,
        int $referenceImageCount = 1,
        bool $priceAsFrom = true,
    ): string {
        $details = $this->productDetailsText($article, $manufacturer?->name, $category?->name);

        if ($fromReferenceImage && $referenceImageCount > 1) {
            $prompt = 'Erstelle aus diesen ' . $referenceImageCount . ' Produktfotos ein professionelles Werbebild für Social Media (z. B. Instagram). '
                . 'Alle Fotos zeigen dasselbe Produkt aus einem Veranstaltungstechnik-Verleih aus verschiedenen Blickwinkeln: ' . $details . ' '
                . 'Nutze die Fotos als verbindliche Vorlage für die korrekte Darstellung: Form, Proportionen, Farben, Bedienelemente, '
                . 'Anschlüsse und Beschriftungen des Produkts müssen exakt den Fotos entsprechen, erfinde keine Details hinzu. '
                . 'Zeige das Produkt einmal in einer ansprechenden Perspektive vor einem modernen, stimmungsvollen Bühnen- oder '
                . 'Veranstaltungshintergrund (z. B. Lichtstrahlen, Bokeh) mit professioneller Beleuchtung.';
        } elseif ($fromReferenceImage) {
            $prompt = 'Style dieses Produktfoto zu einem professionellen Werbebild für Social Media (z. B. Instagram) um. '
                . 'Es zeigt folgendes Produkt aus einem Veranstaltungstechnik-Verleih: ' . $details . ' '
                . 'Behalte das Produkt und sein Erscheinungsbild exakt bei, verändere nur Hintergrund, Beleuchtung und Stimmung: '
                . 'ein moderner, stimmungsvoller Bühnen- oder Veranstaltungshintergrund (z. B. Lichtstrahlen, Bokeh).';
        } else {
            $prompt = 'Erstelle ein professionelles, hochwertiges Werbebild für Social Media (z. B. Instagram) für folgendes '
                . 'Produkt aus einem Veranstaltungstechnik-Verleih: ' . $details . ' '
                . 'Stil: realistische, moderne Produktfotografie mit ansprechender Beleuchtung und einem leicht abgedunkelten, '
                . 'stimmungsvollen Bühnen- oder Veranstaltungshintergrund (z. B. Lichtstrahlen, Bokeh), das Produkt steht klar im Fokus.';
        }

        $properties = $includeProperties ? $this->propertyLines($article, $manufacturer, $category) : [];
        $price = $includePrice ? $this->priceText($article, $priceAsFrom) : null;
        $showName = $includeArticleName;

        if (!$showName && $properties === [] && $price === null) {
            $prompt .= ' Keine Logos, kein Text und keine Wasserzeichen im Bild.';
        } else {
            $prompt .= ' Gestalte das Bild als Werbegrafik mit einem gut lesbaren, modernen Text-Overlay, '
                . 'das das Produkt nicht verdeckt (z. B. am oberen oder unteren Bildrand).';
            if ($showName) {
                $prompt .= ' Schreibe die Produktbezeichnung exakt so, wie sie hier steht, als markante Überschrift ins Bild: "'
                    . $this->quote(ArticleDisplayName::of($article, $manufacturer)) . '".';
            }
            if ($properties !== []) {
                $prompt .= ' Führe darunter folgende Eigenschaften als kurze, übersichtliche Liste auf, '
                    . 'wörtlich und ohne weitere Angaben zu erfinden: '
                    . implode('; ', array_map(fn (string $line): string => '"' . $this->quote($line) . '"', $properties)) . '.';
            }
            if ($price !== null) {
                $prompt .= ' Zeige den Mietpreis gut sichtbar als Preis-Hinweis (z. B. als Preis-Etikett), '
                    . 'wörtlich und ohne ihn zu verändern: "' . $this->quote($price) . '".';
            }
            $prompt .= ' Achte auf korrekte Rechtschreibung. Darüber hinaus keine weiteren Texte, keine Logos und keine Wasserzeichen im Bild.';
        }

        if ($additionalInstructions !== null) {
            $prompt .= ' Zusätzliche Anweisungen: ' . $additionalInstructions;
        }

        return $prompt;
    }

    /**
     * Key properties rendered into the image; empty fields are skipped. The
     * rental price is a separate option, see priceText().
     *
     * @return list<string>
     */
    public function propertyLines(Article $article, ?Manufacturer $manufacturer, ?Category $category): array
    {
        $lines = [];
        if ($manufacturer !== null && !$manufacturer->isGeneric) {
            $lines[] = 'Hersteller: ' . $manufacturer->name;
        }
        if ($category !== null) {
            $lines[] = 'Kategorie: ' . $category->name;
        }
        if ($article->weightValue !== null && trim($article->weightValue) !== '' && $article->weightUnit !== null) {
            $lines[] = 'Gewicht: ' . $this->formatDecimal($article->weightValue) . ' ' . $article->weightUnit;
        }
        if ($article->dimensions !== null && trim($article->dimensions) !== '') {
            $lines[] = 'Maße: ' . trim($article->dimensions);
        }
        if ($article->is19Inch) {
            $rack = 'Rackeinbau: ' . $article->rackWidth->label();
            if ($article->heightUnits !== null && $article->heightUnits > 0) {
                $rack .= ', ' . $article->heightUnits . ' HE';
            }
            $lines[] = $rack;
        }
        return $lines;
    }

    /**
     * Daily rental price as rendered into the image, or null when the article
     * has no (billable) price. With $asFrom (default) it is phrased as a
     * starting price ("ab 25,00 €").
     */
    public function priceText(Article $article, bool $asFrom = true): ?string
    {
        if (!$article->isBillable || $article->dailyRentalPrice === null || trim($article->dailyRentalPrice) === '') {
            return null;
        }

        return 'Miete: ' . ($asFrom ? 'ab ' : '') . number_format((float) $article->dailyRentalPrice, 2, ',', '.') . ' € pro Tag';
    }

    private function productDetailsText(Article $article, ?string $manufacturerName, ?string $categoryName): string
    {
        $parts = [$article->name];
        if ($manufacturerName !== null) {
            $parts[] = 'Hersteller: ' . $manufacturerName;
        }
        if ($categoryName !== null) {
            $parts[] = 'Kategorie: ' . $categoryName;
        }
        if ($article->description !== null && trim($article->description) !== '') {
            $parts[] = 'Beschreibung: ' . trim($article->description);
        }

        return implode('. ', $parts) . '.';
    }

    /** "2.500" -> "2,5", "12.00" -> "12" */
    private function formatDecimal(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return str_replace('.', ',', $value);
    }

    /** Text is placed inside double quotes in the prompt; avoid breaking them. */
    private function quote(string $text): string
    {
        return str_replace('"', "'", trim($text));
    }
}
