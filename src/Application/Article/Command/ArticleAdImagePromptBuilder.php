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
 * display name and/or a short list of key properties (manufacturer,
 * category, weight, dimensions, rack data, daily rental price) are rendered
 * into the image as a text overlay.
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
    ): string {
        $details = $this->productDetailsText($article, $manufacturer?->name, $category?->name);

        if ($fromReferenceImage) {
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
        $showName = $includeArticleName;

        if (!$showName && $properties === []) {
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
            $prompt .= ' Achte auf korrekte Rechtschreibung. Darüber hinaus keine weiteren Texte, keine Logos und keine Wasserzeichen im Bild.';
        }

        if ($additionalInstructions !== null) {
            $prompt .= ' Zusätzliche Anweisungen: ' . $additionalInstructions;
        }

        return $prompt;
    }

    /**
     * Key properties rendered into the image; empty fields are skipped.
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
        if ($article->isBillable && $article->dailyRentalPrice !== null && trim($article->dailyRentalPrice) !== '') {
            $lines[] = 'Miete: ' . number_format((float) $article->dailyRentalPrice, 2, ',', '.') . ' € pro Tag';
        }

        return $lines;
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
