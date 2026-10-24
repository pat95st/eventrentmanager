<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\Offer\Query\OfferDetail;
use App\Application\Offer\Query\OfferRow;
use App\Domain\Customer\Customer;
use App\Domain\Offer\OfferFreeTextItem;
use App\Domain\Offer\OfferSectionText;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\LegalSettings;
use App\Domain\Settings\OfferPdfSettings;
use App\Domain\Settings\PdfDesignSettings;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Erzeugt ein Angebot als PDF: Firmenlogo oben rechts, "Angebot"-Kopfdaten
 * (Angebotsnummer/Datum/gültig bis), optionalen Einleitungstext
 * (OfferPdfSettings::$introHtml), Kunde + Veranstaltungsdaten, eine
 * Positionstabelle (Artikel, Menge, Einzelpreis, Zeilensumme) mit
 * Seitenumbruch-Behandlung für lange Listen (gleiche Umbruchlogik wie
 * PackingListPdfGenerator), eine Gesamtsumme am Ende, optional gefolgt von
 * einem Schlusstext (OfferPdfSettings::$closingHtml). Optional werden
 * zuletzt die hinterlegten Vermietbedingungen
 * (LegalSettings::$rentalTermsMarkdown) als einfacher Text angehängt.
 *
 * OfferPdfSettings steuert ausschließlich den Text IM PDF-Dokument selbst —
 * nicht zu verwechseln mit der "Angebot per E-Mail"-Vorlage (siehe
 * EmailTemplateKey::OfferPdf), die den Text der E-Mail steuert, in der
 * dieses PDF als Anhang verschickt wird.
 *
 * Einleitungs-/Schlusstext (OfferPdfSettings::$introHtml/$closingHtml) sind
 * echtes, vom Editor geliefertes Rich-Text-HTML (fett/kursiv/unterstrichen/
 * Überschriften/Listen/Ausrichtung) und werden über RichTextPdfRenderer
 * gezeichnet — NICHT mehr über die stark vereinfachte reine
 * Text-Darstellung, die früher hier für Markdown verwendet wurde (siehe
 * plainTextFromMarkdown(), die für die weiterhin als Markdown gepflegten
 * Vermietbedingungen (LegalSettings::$rentalTermsMarkdown) erhalten bleibt).
 *
 * Nutzt bewusst dieselbe Low-Level-Schreibmechanik (SimplePdfWriter) wie
 * PackingListPdfGenerator, statt einen eigenen PDF-Writer zu bauen.
 */
final class OfferPdfGenerator
{
    private const MARGIN = 40.0;
    private const LOGO_MAX_WIDTH = 120.0;
    private const LOGO_MAX_HEIGHT = 50.0;
    private const TABLE_FONT_SIZE = 8.5;
    private const TABLE_LINE_HEIGHT = 11.0;
    private const TABLE_ROW_PADDING = 6.0;

    // DIN 5008 (Faltmarken + Fensterkuvert-Adresszone), siehe
    // InvoicePdfGenerator für die identische Begründung.
    private const MM_TO_PT = 2.83465;
    private const FOLD_MARK_LENGTH_MM = 4.0;
    private const FOLD_MARK_AT_MM = [105.0, 210.0];
    private const WINDOW_ZONE_LEFT_MM = 20.0;
    private const WINDOW_ZONE_TOP_MM = 45.0;
    private const WINDOW_ZONE_WIDTH_MM = 85.0;
    private const WINDOW_ZONE_BOTTOM_MM = 90.0;

    public function __construct(
        private readonly LocalFileStorage $fileStorage,
        private readonly RichTextPdfRenderer $richTextRenderer,
    ) {
    }

    public function generate(OfferDetail $detail, CompanySettings $companySettings, ?LegalSettings $legalSettings = null, ?OfferPdfSettings $offerPdfSettings = null, ?PdfDesignSettings $pdfDesignSettings = null): string
    {
        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();

        $logoJpeg = $this->prepareLogoJpeg($companySettings);
        $logoBox = $logoJpeg !== null ? $this->fitBox($logoJpeg, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT) : null;

        $rows = $this->buildRows($detail);
        $pageNumber = 1;

        $writer->addPage();
        $y = $this->drawHeader($writer, $detail, $companySettings, $logoJpeg, $logoBox, $pageWidth, $pageHeight, $pageNumber, continuation: false, offerPdfSettings: $offerPdfSettings, pdfDesignSettings: $pdfDesignSettings);
        $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings);

        if ($rows === []) {
            $writer->text(self::MARGIN, $y - self::TABLE_LINE_HEIGHT, 'Keine Positionen erfasst.', 10);
            $y -= self::TABLE_LINE_HEIGHT + self::TABLE_ROW_PADDING;
        }

        foreach ($rows as $row) {
            $rowLineCount = max(count($row['nameLines']), 1);
            $rowHeight = $rowLineCount * self::TABLE_LINE_HEIGHT + self::TABLE_ROW_PADDING;

            if ($y - $rowHeight < self::MARGIN + 60) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings);
            }

            $this->drawRow($writer, $y, $row);
            $y -= $rowHeight;
        }

        // Reservierter Platz für den Summenblock: Zwischensumme + ggf.
        // Zubehörpauschale-/Kombirabatt-Zeile + Gesamtsumme (bis zu 4 Zeilen).
        if ($y - 70 < self::MARGIN + 60) {
            $pageNumber++;
            $writer->addPage();
            $y = $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
        }

        // Zentrale Summenberechnung (Mietpreise -> Zubehörpauschale ->
        // Kombirabatt -> Nettosumme), siehe App\Domain\Pricing\DocumentTotals
        // — ersetzt die vormals hier duplizierte Ad-hoc-Berechnung.
        $totals = $detail->totals();

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
        $y -= 20;

        $writer->text($pageWidth - self::MARGIN - 230, $y, 'Zwischensumme Mietpreise:', 9);
        $writer->text($pageWidth - self::MARGIN - 75, $y, number_format($totals->rentalNetTotal, 2, ',', '.') . ' EUR', 9);
        $y -= 14;

        if ($detail->offer->accessoryFlatRateEnabled && $detail->offer->accessoryFlatRatePercent !== null) {
            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Zubehörpauschale (' . number_format((float) $detail->offer->accessoryFlatRatePercent, 2, ',', '.') . '%):', 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format($totals->accessoryFlatRateAmount, 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        if ($detail->offer->discountType !== null && $detail->offer->discountValue !== null) {
            $discountLabel = $detail->offer->discountType === 'percent'
                ? 'Kombirabatt (' . number_format((float) $detail->offer->discountValue, 2, ',', '.') . '%):'
                : 'Kombirabatt (Festbetrag):';
            $writer->text($pageWidth - self::MARGIN - 230, $y, $discountLabel, 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '-' . number_format($totals->discountAmount, 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        foreach ($detail->laborItems as $laborEntry) {
            if ($y - 14 < self::MARGIN + 40) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                $y -= 20;
            }

            $laborLabel = $laborEntry->laborTimeArticle->name . ' ('
                . number_format((float) $laborEntry->item->hours, 2, ',', '.') . ' Std. à '
                . number_format((float) $laborEntry->item->unitRatePerHour, 2, ',', '.') . ' EUR):';
            $writer->text($pageWidth - self::MARGIN - 230, $y, $laborLabel, 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format($laborEntry->lineTotal(), 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        // Verkaufspositionen (siehe App\Domain\Offer\OfferSaleItem) erscheinen,
        // wie die Arbeitszeit-Positionen, als eigene Zeile NACH dem
        // Kombirabatt — sie sind Einmalpreise und nicht Teil der
        // Mietpreis-Zwischensumme (siehe DocumentTotals-Klassen-Doc).
        foreach ($detail->saleItems as $saleEntry) {
            if ($y - 14 < self::MARGIN + 40) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                $y -= 20;
            }

            $saleLabel = $saleEntry->article->name . ' (' . (string) $saleEntry->item->quantity . 'x, Verkauf):';
            $writer->text($pageWidth - self::MARGIN - 230, $y, $saleLabel, 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format($saleEntry->lineTotal(), 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        // Freitext-Posten (orderedRows()-Konzept): erscheinen in der Tabelle
        // oben zusammen mit den Mietpositionen, hier im Summenblock aber wie
        // Arbeitszeit-/Verkaufspositionen als eigene Zeile NACH dem
        // Kombirabatt (siehe DocumentTotals::$freeTextTotal).
        if ($detail->freeTextTotal() > 0) {
            if ($y - 14 < self::MARGIN + 40) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                $y -= 20;
            }

            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Freitext-Posten:', 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format($detail->freeTextTotal(), 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        $writer->text($pageWidth - self::MARGIN - 230, $y, 'Gesamtsumme (netto):', 10, true);
        $writer->text($pageWidth - self::MARGIN - 75, $y, number_format($totals->grossTotal, 2, ',', '.') . ' EUR', 10, true);
        $y -= 30;

        $closingBlocks = $this->richTextRenderer->parse($offerPdfSettings?->closingHtml);
        if ($closingBlocks !== []) {
            $closingPageNumber = $pageNumber;
            $y = $this->richTextRenderer->render(
                $writer,
                $closingBlocks,
                self::MARGIN,
                $y - 10.0,
                $pageWidth - 2 * self::MARGIN,
                self::MARGIN + 20,
                function () use ($writer, $detail, $pageWidth, $pageHeight, $pdfDesignSettings, &$closingPageNumber): float {
                    $closingPageNumber++;
                    $writer->addPage();

                    return $this->drawHeader($writer, $detail, null, null, null, $pageWidth, $pageHeight, $closingPageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                }
            );
        }

        $termsText = $this->plainTextFromMarkdown($legalSettings?->rentalTermsMarkdown);
        if ($termsText !== null) {
            $y = $this->drawTerms($writer, $detail, $termsText, $y, $pageWidth, $pageHeight, $pageNumber, $pdfDesignSettings);
        }

        return $writer->output();
    }

    /** @return array<int, array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}> */
    private function buildRows(OfferDetail $detail): array
    {
        $columns = $this->columnLayout();
        $rows = [];

        foreach ($detail->orderedRows() as $row) {
            if ($row->type === OfferRow::TYPE_SECTION) {
                /** @var OfferSectionText $sectionText */
                $sectionText = $row->data;
                $rows[] = [
                    'nameLines' => $this->wrapText($sectionText->heading, $columns['article']['width'], self::TABLE_FONT_SIZE),
                    'quantity' => '',
                    'unitPrice' => '',
                    'lineTotal' => '',
                    'isSection' => true,
                ];

                continue;
            }

            if ($row->type === OfferRow::TYPE_FREE_TEXT) {
                /** @var OfferFreeTextItem $item */
                $item = $row->data;
                $rows[] = [
                    'nameLines' => $this->wrapText($item->description, $columns['article']['width'], self::TABLE_FONT_SIZE),
                    'quantity' => rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ','),
                    'unitPrice' => number_format((float) $item->unitPrice, 2, ',', '.') . ' EUR',
                    'lineTotal' => number_format($item->lineTotal(), 2, ',', '.') . ' EUR',
                    'isSection' => false,
                ];

                continue;
            }

            $entry = $row->data;
            $rows[] = [
                'nameLines' => $this->wrapText($entry->article->name, $columns['article']['width'], self::TABLE_FONT_SIZE),
                'quantity' => (string) $entry->item->quantity,
                'unitPrice' => number_format((float) $entry->item->unitPricePerDay, 2, ',', '.') . ' EUR',
                'lineTotal' => number_format($entry->lineTotal(), 2, ',', '.') . ' EUR',
                'isSection' => false,
            ];
        }

        return $rows;
    }

    private function drawHeader(
        SimplePdfWriter $writer,
        OfferDetail $detail,
        ?CompanySettings $companySettings,
        ?string $logoJpeg,
        ?array $logoBox,
        float $pageWidth,
        float $pageHeight,
        int $pageNumber,
        bool $continuation = false,
        ?OfferPdfSettings $offerPdfSettings = null,
        ?PdfDesignSettings $pdfDesignSettings = null
    ): float {
        // Faltmarken (DIN 5008) werden auf JEDER Seite gezeichnet (siehe
        // InvoicePdfGenerator für die identische Begründung).
        $this->drawFoldMarks($writer, $pageHeight);

        if ($logoJpeg !== null && $logoBox !== null) {
            // Logo-Position (links/rechts oben) laut PdfDesignSettings, siehe
            // dortigen Klassen-Doc — Default 'right' entspricht optisch dem
            // bisherigen, fest verdrahteten Verhalten. Kollidiert auf der
            // linken Seite NICHT mit der Fensterkuvert-Adresszone (siehe
            // drawWindowEnvelopeAddress()), da das Logo im oberen Seitenrand
            // (bis ~32mm von oben) sitzt und die Fensterzone erst bei 45mm
            // beginnt.
            $logoX = $pdfDesignSettings?->logoPosition === PdfDesignSettings::LOGO_POSITION_LEFT
                ? self::MARGIN
                : $pageWidth - self::MARGIN - $logoBox[0];
            $writer->image(
                $logoJpeg,
                $logoX,
                $pageHeight - self::MARGIN - $logoBox[1],
                $logoBox[0],
                $logoBox[1]
            );
        }

        // Fensterkuvert-Adresszone: NUR auf der ersten Seite.
        if (!$continuation && $companySettings !== null) {
            $this->drawWindowEnvelopeAddress($writer, $detail, $companySettings, $pageHeight);
        }

        // ENTSCHEIDUNG (Kollisionsvermeidung): siehe InvoicePdfGenerator —
        // identisches Muster. Titel/Kopfdatenblock beginnt auf Seite 1 erst
        // unterhalb der Fensterzone (y=90mm), auf Folgeseiten wie bisher.
        $y = $continuation
            ? $pageHeight - self::MARGIN
            : $pageHeight - (self::WINDOW_ZONE_BOTTOM_MM * self::MM_TO_PT) - 10.0;
        $title = $continuation ? 'Angebot (Fortsetzung)' : 'Angebot';
        $writer->text(self::MARGIN, $y - 4, $title, 18, true, color: $pdfDesignSettings?->accentColorPdfString() ?? '0 0 0');
        $y -= 30;

        if (!$continuation) {
            foreach ($this->buildOfferInfoLines($detail) as $line) {
                $writer->text(self::MARGIN, $y, $line, 10);
                $y -= 14;
            }

            // Kein Seitenumbruch-Callback: der Einleitungstext wird (wie schon
            // vor der Rich-Text-Umstellung) nicht separat paginiert — bei sehr
            // langen Einleitungstexten kann er in die Positionstabelle laufen,
            // siehe RichTextPdfRenderer-Klassendoc.
            $introBlocks = $this->richTextRenderer->parse($offerPdfSettings?->introHtml);
            if ($introBlocks !== []) {
                $y -= 6;
                $y = $this->richTextRenderer->render($writer, $introBlocks, self::MARGIN, $y, $pageWidth - 2 * self::MARGIN, self::MARGIN + 60);
            }
        }

        $writer->text($pageWidth - self::MARGIN - 50, $pageHeight - self::MARGIN - 60, 'Seite ' . $pageNumber, 8);

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
        $y -= 20;

        return $y;
    }

    /** @return string[] */
    private function buildOfferInfoLines(OfferDetail $detail): array
    {
        $offer = $detail->offer;
        $lines = [];
        $lines[] = 'Angebotsnummer: ' . strtoupper(substr($offer->id, 0, 8));
        $lines[] = 'Datum: ' . $offer->createdAt->format('d.m.Y');

        if ($offer->validUntil !== null) {
            $lines[] = 'Gültig bis: ' . $offer->validUntil->format('d.m.Y');
        }

        // Die vormalige Zeile "Kunde: ..." entfällt hier — der Kunde steht
        // jetzt im Adressblock der Fensterkuvert-Zone (siehe
        // drawWindowEnvelopeAddress()).

        if ($offer->eventLocation !== null && trim($offer->eventLocation) !== '') {
            $lines[] = 'Ort: ' . $offer->eventLocation;
        }

        $lines[] = 'Zeitraum: ' . $offer->startDate->format('d.m.Y') . ' - ' . $offer->endDate->format('d.m.Y');

        if ($offer->note !== null && trim($offer->note) !== '') {
            $lines[] = 'Anmerkung: ' . $offer->note;
        }

        return $lines;
    }

    /**
     * Zwei kurze waagerechte Faltmarken (DIN 5008), siehe InvoicePdfGenerator
     * für die identische Begründung (bewusst dupliziert, siehe Klassen-Doc).
     */
    private function drawFoldMarks(SimplePdfWriter $writer, float $pageHeight): void
    {
        foreach (self::FOLD_MARK_AT_MM as $mmFromTop) {
            $y = $pageHeight - ($mmFromTop * self::MM_TO_PT);
            $writer->line(0.0, $y, self::FOLD_MARK_LENGTH_MM * self::MM_TO_PT, $y, 0.5, '0.5 0.5 0.5');
        }
    }

    /** Fensterkuvert-Adresszone (DIN 5008), siehe InvoicePdfGenerator für die identische Begründung. */
    private function drawWindowEnvelopeAddress(SimplePdfWriter $writer, OfferDetail $detail, CompanySettings $companySettings, float $pageHeight): void
    {
        $zoneX = self::WINDOW_ZONE_LEFT_MM * self::MM_TO_PT;
        $zoneTopY = $pageHeight - (self::WINDOW_ZONE_TOP_MM * self::MM_TO_PT);

        $returnLine = $this->buildReturnAddressLine($companySettings);
        $y = $zoneTopY - 7.0;
        if ($returnLine !== null) {
            $writer->text($zoneX, $y, $returnLine, 7.5);
        }
        $y -= 18.0;

        foreach ($this->buildCustomerAddressLines($detail->customer) as $line) {
            $writer->text($zoneX, $y, $line, 10.0);
            $y -= 12.5;
        }
    }

    /** Einzeilige Rücksendeangabe "Firma, Straße, PLZ Ort" — siehe InvoicePdfGenerator für die identische Begründung. */
    private function buildReturnAddressLine(CompanySettings $companySettings): ?string
    {
        $parts = [];
        if ($companySettings->name !== null && trim($companySettings->name) !== '') {
            $parts[] = trim($companySettings->name);
        }
        if ($companySettings->street !== null && trim($companySettings->street) !== '') {
            $parts[] = trim($companySettings->street);
        }
        $cityLine = trim(($companySettings->postalCode ?? '') . ' ' . ($companySettings->city ?? ''));
        if ($cityLine !== '') {
            $parts[] = $cityLine;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /** @return string[] */
    private function buildCustomerAddressLines(?Customer $customer): array
    {
        if ($customer === null) {
            return ['Noch nicht zugeordnet'];
        }

        $lines = [$customer->displayName()];

        if ($customer->addressLine2 !== null && trim($customer->addressLine2) !== '') {
            $lines[] = trim($customer->addressLine2);
        }
        if ($customer->street !== null && trim($customer->street) !== '') {
            $lines[] = trim($customer->street);
        }
        $cityLine = trim(($customer->postalCode ?? '') . ' ' . ($customer->city ?? ''));
        if ($cityLine !== '') {
            $lines[] = $cityLine;
        }

        return $lines;
    }

    /** @return array{article: array{x: float, width: float}, quantity: array{x: float, width: float}, unitPrice: array{x: float, width: float}, lineTotal: array{x: float, width: float}} */
    private function columnLayout(): array
    {
        return [
            'article' => ['x' => self::MARGIN, 'width' => 250.0],
            'quantity' => ['x' => self::MARGIN + 260.0, 'width' => 55.0],
            'unitPrice' => ['x' => self::MARGIN + 325.0, 'width' => 90.0],
            'lineTotal' => ['x' => self::MARGIN + 425.0, 'width' => 90.0],
        ];
    }

    private function drawTableHeader(SimplePdfWriter $writer, float $y, float $pageWidth, ?PdfDesignSettings $pdfDesignSettings = null): float
    {
        $columns = $this->columnLayout();

        $writer->filledRect(self::MARGIN, $y - 14, $pageWidth - 2 * self::MARGIN, 18, $pdfDesignSettings?->accentColorTintPdfString() ?? '0.92 0.92 0.92');
        $writer->text($columns['article']['x'] + 2, $y - 8, 'Artikel', 9, true);
        $writer->text($columns['quantity']['x'] + 2, $y - 8, 'Menge', 9, true);
        $writer->text($columns['unitPrice']['x'] + 2, $y - 8, 'Einzelpreis', 9, true);
        $writer->text($columns['lineTotal']['x'] + 2, $y - 8, 'Summe', 9, true);

        return $y - 22;
    }

    /** @param array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool} $row */
    private function drawRow(SimplePdfWriter $writer, float $topY, array $row): void
    {
        $columns = $this->columnLayout();
        $baseline = $topY - self::TABLE_LINE_HEIGHT + 2;

        foreach ($row['nameLines'] as $i => $line) {
            $writer->text($columns['article']['x'] + 2, $baseline - $i * self::TABLE_LINE_HEIGHT, $line, self::TABLE_FONT_SIZE, $row['isSection']);
        }

        if (!$row['isSection']) {
            $writer->text($columns['quantity']['x'] + 2, $baseline, $row['quantity'], self::TABLE_FONT_SIZE);
            $writer->text($columns['unitPrice']['x'] + 2, $baseline, $row['unitPrice'], self::TABLE_FONT_SIZE);
            $writer->text($columns['lineTotal']['x'] + 2, $baseline, $row['lineTotal'], self::TABLE_FONT_SIZE);
        }
    }

    private function drawTerms(SimplePdfWriter $writer, OfferDetail $detail, string $termsText, float $y, float $pageWidth, float $pageHeight, int $pageNumber, ?PdfDesignSettings $pdfDesignSettings = null): float
    {
        $writer->text(self::MARGIN, $y, 'Vermietbedingungen', 11, true);
        $y -= 18;

        $lines = $this->wrapText($termsText, $pageWidth - 2 * self::MARGIN, 8.0, true);

        foreach ($lines as $line) {
            if ($y < self::MARGIN + 20) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, null, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
            }
            $writer->text(self::MARGIN, $y, $line, 8.0);
            $y -= 11.0;
        }

        return $y;
    }

    /**
     * Reduziert einen Markdown-Text (ausschließlich noch für
     * LegalSettings::$rentalTermsMarkdown verwendet — Einleitungs-/
     * Schlusstext laufen seit der Rich-Text-Umstellung über
     * RichTextPdfRenderer, siehe Klassendoc) grob auf reinen Text fürs PDF
     * (kein Markdown-Rendering nötig für dieses Dokument — MarkdownRenderer
     * erzeugt nur HTML, siehe App\Infrastructure\Http\MarkdownRenderer):
     * einfache Syntaxzeichen werden entfernt, ohne Anspruch auf ein
     * vollständiges Markdown-Parsing. Best-effort, siehe Aufgabenstellung.
     */
    private function plainTextFromMarkdown(?string $markdown): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        $text = preg_replace('/^#{1,6}\s*/m', '', $markdown) ?? $markdown;
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '$1', $text) ?? $text;
        $text = preg_replace('/^[-*]\s+/m', '- ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1 ($2)', $text) ?? $text;

        return trim($text);
    }

    /** @return string[] */
    private function wrapText(string $text, float $maxWidth, float $fontSize, bool $keepParagraphs = false): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }

        $avgCharWidth = $fontSize * 0.52;
        $maxChars = max(6, (int) floor($maxWidth / $avgCharWidth));

        if ($keepParagraphs) {
            $lines = [];
            foreach (explode("\n", str_replace("\r\n", "\n", $text)) as $paragraph) {
                if (trim($paragraph) === '') {
                    $lines[] = '';

                    continue;
                }
                $wrapped = wordwrap($paragraph, $maxChars, "\n", true);
                foreach (explode("\n", $wrapped) as $line) {
                    $lines[] = $line;
                }
            }

            return $lines;
        }

        $wrapped = wordwrap($text, $maxChars, "\n", true);

        return explode("\n", $wrapped);
    }

    private function prepareLogoJpeg(CompanySettings $companySettings): ?string
    {
        if ($companySettings->logoFilename === null || !extension_loaded('gd')) {
            return null;
        }

        $absolutePath = $this->fileStorage->absolutePath('company', $companySettings->logoFilename);
        if (!is_file($absolutePath)) {
            return null;
        }

        $data = @file_get_contents($absolutePath);
        if ($data === false || $data === '') {
            return null;
        }

        $source = @imagecreatefromstring($data);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);

            return null;
        }

        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 88);
        $jpegBytes = ob_get_clean();
        imagedestroy($canvas);

        return $jpegBytes === false || $jpegBytes === '' ? null : $jpegBytes;
    }

    /** @return array{0: float, 1: float} */
    private function fitBox(string $jpegBytes, float $maxWidth, float $maxHeight): array
    {
        $size = @getimagesizefromstring($jpegBytes);
        if ($size === false) {
            return [$maxWidth, $maxHeight];
        }

        [$pixelWidth, $pixelHeight] = $size;
        if ($pixelWidth <= 0 || $pixelHeight <= 0) {
            return [$maxWidth, $maxHeight];
        }

        $scale = min($maxWidth / $pixelWidth, $maxHeight / $pixelHeight);

        return [$pixelWidth * $scale, $pixelHeight * $scale];
    }
}
