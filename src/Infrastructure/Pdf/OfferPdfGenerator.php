<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\Offer\Query\OfferDetail;
use App\Application\Offer\Query\OfferRow;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerContact;
use App\Domain\Offer\OfferFreeTextItem;
use App\Domain\Offer\OfferSectionText;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\LegalSettings;
use App\Domain\Settings\OfferPdfSettings;
use App\Domain\Settings\PdfDesignSettings;
use App\Domain\Settings\PdfLayout\PdfLayoutSettings;
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
    // Unterste Grenze für Inhalt (Summen, Texte, Bedingungen) — darunter
    // liegt die Fußzeile (siehe DocumentFooter::TOP_Y).
    private const CONTENT_BOTTOM = DocumentFooter::TOP_Y + 12.0;
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
        private readonly ?DocumentFooter $footer = null,
        private readonly ?PdfAmountFormatter $amountFormatter = null,
    ) {
    }

    public function generate(OfferDetail $detail, CompanySettings $companySettings, ?LegalSettings $legalSettings = null, ?OfferPdfSettings $offerPdfSettings = null, ?PdfDesignSettings $pdfDesignSettings = null, ?PdfLayoutSettings $layout = null): string
    {
        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();

        $logoJpeg = $this->prepareLogoJpeg($companySettings);
        $logoBox = $logoJpeg !== null ? $this->fitBox($logoJpeg, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT) : null;

        $rows = $this->buildRows($detail);
        $pageNumber = 1;

        $writer->addPage();
        $y = $this->drawHeader($writer, $detail, $companySettings, $logoJpeg, $logoBox, $pageWidth, $pageHeight, $pageNumber, continuation: false, offerPdfSettings: $offerPdfSettings, pdfDesignSettings: $pdfDesignSettings, layout: $layout);
        $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings);

        if ($rows === []) {
            $writer->text(self::MARGIN, $y - self::TABLE_LINE_HEIGHT, 'Keine Positionen erfasst.', 10);
            $y -= self::TABLE_LINE_HEIGHT + self::TABLE_ROW_PADDING;
        }

        foreach ($rows as $row) {
            $rowHeight = ArticlePdfRow::contentHeight($row, self::TABLE_LINE_HEIGHT) + self::TABLE_ROW_PADDING;

            if ($y - $rowHeight < self::MARGIN + 60) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings);
            }

            $this->drawRow($writer, $y, $row);
            $y -= $rowHeight;
        }

        // Seitenumbruch-Helfer für den Summen-/Tabellenbereich unterhalb
        // der Positionsliste (Folgeseite mit verkürztem Kopf).
        $newPage = function () use ($writer, $detail, $pageWidth, $pageHeight, $pdfDesignSettings, &$pageNumber): float {
            $pageNumber++;
            $writer->addPage();

            return $this->drawHeader($writer, $detail, null, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings) - 20;
        };

        // Reservierter Platz für den Summenblock: Zwischensumme + ggf.
        // Zubehörpauschale-/Kombirabatt-Zeile.
        if ($y - 70 < self::MARGIN + 60) {
            $y = $newPage() + 20;
        }

        // Zentrale Summenberechnung (Mietpreise -> Zubehörpauschale ->
        // Kombirabatt -> Nettosumme), siehe App\Domain\Pricing\DocumentTotals.
        $totals = $detail->totals();

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
        $y -= 20;

        // Nutzerwunsch: EINE "Zwischensumme" über alle Positionen der
        // Tabelle oben — Mietpositionen UND Freitext-Posten (die ohnehin
        // gemeinsam in die Basis für Zubehörpauschale/Kombirabatt
        // eingehen, siehe DocumentTotals::calculate()); keine eigene
        // "Freitext-Posten"-Zeile mehr.
        $this->drawSummaryLine($writer, $pageWidth, $y, 'Zwischensumme:', $this->formatAmount($totals->rentalNetTotal + $totals->freeTextTotal));
        $y -= 14;

        if ($detail->offer->accessoryFlatRateEnabled && $detail->offer->accessoryFlatRatePercent !== null) {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Zubehörpauschale (' . number_format((float) $detail->offer->accessoryFlatRatePercent, 2, ',', '.') . '%):', '+' . $this->formatAmount($totals->accessoryFlatRateAmount));
            $y -= 14;
        }

        if ($detail->offer->discountType !== null && $detail->offer->discountValue !== null) {
            $discountLabel = $detail->offer->discountType === 'percent'
                ? 'Kombirabatt (' . number_format((float) $detail->offer->discountValue, 2, ',', '.') . '%):'
                : 'Kombirabatt (Festbetrag):';
            $this->drawSummaryLine($writer, $pageWidth, $y, $discountLabel, '-' . $this->formatAmount($totals->discountAmount));
            $y -= 14;
        }

        // Nutzerwunsch: Arbeitszeit- und Verkaufspositionen als eigene
        // Tabellen (analog zur Artikeltabelle) statt als lange
        // Summenzeilen, die sich mit den Beträgen überschnitten haben.
        $hasExtraTables = false;
        $laborRows = $this->buildLaborRows($detail);
        if ($laborRows !== []) {
            $y = $this->drawTableSection($writer, $y, $pageWidth, 'Arbeitszeit', ['Leistung', 'Menge', 'Einzelpreis', 'Summe'], $laborRows, $newPage, $pdfDesignSettings);
            $hasExtraTables = true;
        }

        $saleRows = $this->buildSaleRows($detail);
        if ($saleRows !== []) {
            $y = $this->drawTableSection($writer, $y, $pageWidth, 'Verkaufspositionen', ['Artikel', 'Menge', 'Einzelpreis', 'Summe'], $saleRows, $newPage, $pdfDesignSettings);
            $hasExtraTables = true;
        }

        if ($y - 70 < self::CONTENT_BOTTOM) {
            $y = $newPage();
        }
        if ($hasExtraTables) {
            $y -= 6;
            $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
            $y -= 20;
        } else {
            $y -= 4;
        }

        // Umsatzsteuer (Rechnungseinstellungen): Kleinunternehmer → eine
        // Gesamtsumme + §19-Hinweis, sonst Netto/USt/Brutto wie auf Rechnungen.
        if ($detail->isSmallBusiness ?? true) {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Gesamtsumme:', $this->formatAmount($totals->grossTotal), 10, true);
            $y -= 16;
            if ($detail->isSmallBusiness === true) {
                $writer->text(self::MARGIN, $y, 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.', 8.5);
                $y -= 14;
            }
            $y -= 14;
        } else {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Nettosumme:', $this->formatAmount($totals->netTotal));
            $y -= 14;
            $this->drawSummaryLine($writer, $pageWidth, $y, 'zzgl. ' . number_format((float) $detail->vatRatePercent, 2, ',', '.') . '% USt:', $this->formatAmount($totals->taxAmount));
            $y -= 16;
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Gesamtbetrag (brutto):', $this->formatAmount($totals->grossTotal), 10, true);
            $y -= 30;
        }

        // Zahlungsbedingungen (Zahlungsart mit Zahlungsziel, Migration 106)
        // unter der Summe — das Zahlungsziel gilt ab Rechnungsstellung.
        if ($detail->paymentMethod !== null) {
            $paymentMethod = $detail->paymentMethod;
            $paymentLines = ['Zahlungsbedingungen: ' . $paymentMethod->name . ' – ' . $paymentMethod->termLabel() . ($paymentMethod->paymentTermDays > 0 ? ' nach Rechnungsstellung' : '')];
            $note = $paymentMethod->renderNote();
            if ($note !== null) {
                foreach ($this->wrapText($note, $pageWidth - 2 * self::MARGIN, 8.5, true) as $noteLine) {
                    $paymentLines[] = $noteLine;
                }
            }
            foreach ($paymentLines as $paymentLine) {
                if ($y < self::CONTENT_BOTTOM) {
                    $y = $newPage();
                }
                $writer->text(self::MARGIN, $y, $paymentLine, 8.5);
                $y -= 12;
            }
            $y -= 10;
        }

        $closingBlocks = $this->richTextRenderer->parse($offerPdfSettings?->closingHtml);
        if ($closingBlocks !== []) {
            $closingOverride = $layout?->get('closing_text');
            $closingX = self::MARGIN;
            $closingY = $y - 10.0;
            $closingWidth = $pageWidth - 2 * self::MARGIN;
            if ($closingOverride !== null) {
                $closingPoint = $closingOverride->toPdfPoint($pageHeight);
                $closingX = $closingPoint['x'];
                $closingY = $closingPoint['y'];
                $closingWidth = $closingOverride->widthPt() ?? $closingWidth;
            }
            $closingPageNumber = $pageNumber;
            $y = $this->richTextRenderer->render(
                $writer,
                $closingBlocks,
                $closingX,
                $closingY,
                $closingWidth,
                self::CONTENT_BOTTOM,
                function () use ($writer, $detail, $pageWidth, $pageHeight, $pdfDesignSettings, &$closingPageNumber): float {
                    $closingPageNumber++;
                    $writer->addPage();

                    return $this->drawHeader($writer, $detail, null, null, null, $pageWidth, $pageHeight, $closingPageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                }
            );
        }

        // Dokumenteigener Schlusstext (Offer::$closingText, Kundenwunsch
        // "Schlusstext" — siehe Aufgabenstellung), bewusst NACH dem globalen
        // Schlusstext (OfferPdfSettings::$closingHtml, oben) gerendert — siehe
        // identische Begründung in InvoicePdfGenerator::generate().
        $documentClosingBlocks = $this->richTextRenderer->parse($detail->offer->closingText);
        if ($documentClosingBlocks !== []) {
            $documentClosingPageNumber = $pageNumber;
            $y = $this->richTextRenderer->render(
                $writer,
                $documentClosingBlocks,
                self::MARGIN,
                $y - 10.0,
                $pageWidth - 2 * self::MARGIN,
                self::CONTENT_BOTTOM,
                function () use ($writer, $detail, $pageWidth, $pageHeight, $pdfDesignSettings, &$documentClosingPageNumber): float {
                    $documentClosingPageNumber++;
                    $writer->addPage();

                    return $this->drawHeader($writer, $detail, null, null, null, $pageWidth, $pageHeight, $documentClosingPageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                }
            );
            $pageNumber = $documentClosingPageNumber;
        }

        $termsText = $this->plainTextFromMarkdown($legalSettings?->rentalTermsMarkdown);
        if ($termsText !== null) {
            $y = $this->drawTerms($writer, $detail, $termsText, $y, $pageWidth, $pageHeight, $pageNumber, $pdfDesignSettings);
        }

        // Fußzeile (Firma | Kontakt/Steuernummer | Bank) auf jeder Seite.
        $this->footer?->drawOnAllPages($writer, null);

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
                    'unitPrice' => number_format((float) $item->unitPrice, 2, ',', '.') . $this->currency(),
                    'lineTotal' => number_format($item->lineTotal(), 2, ',', '.') . $this->currency(),
                    'isSection' => false,
                ];

                continue;
            }

            $entry = $row->data;
            // Bundles (Migration 107): Bestandteile als Zusatzzeile(n)
            // unter dem Bundle-Namen ("enthält: 2× …, 1× …"); Migration 109:
            // Dokumentbild links und Kurztext kursiv darunter.
            $nameColumn = $this->articleRow()->nameColumn(
                $entry->article,
                [
                    $entry->displayName(),
                    isset($detail->bundleComponents[$entry->article->id])
                        ? \App\Application\Article\Query\BundleComponentResolver::describe($detail->bundleComponents[$entry->article->id], (int) $entry->item->quantity)
                        : null,
                ],
                $columns['article']['width'],
                self::TABLE_FONT_SIZE,
                fn (string $text, float $width, float $fontSize): array => $this->wrapText($text, $width, $fontSize),
            );
            $rows[] = [
                'nameLines' => $nameColumn['nameLines'],
                'shortTextLines' => $nameColumn['shortTextLines'],
                'image' => $nameColumn['image'],
                'quantity' => (string) $entry->item->quantity,
                'unitPrice' => number_format((float) $entry->item->unitPricePerDay, 2, ',', '.') . $this->currency(),
                'lineTotal' => number_format($entry->lineTotal(), 2, ',', '.') . $this->currency(),
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
        ?PdfDesignSettings $pdfDesignSettings = null,
        ?PdfLayoutSettings $layout = null
    ): float {
        // Faltmarken (DIN 5008) werden auf JEDER Seite gezeichnet (siehe
        // InvoicePdfGenerator für die identische Begründung).
        $this->drawFoldMarks($writer, $pageHeight);

        if ($logoJpeg !== null && $logoBox !== null) {
            // Logo-Position: per visuellem Editor überschreibbar (siehe
            // App\Domain\Settings\PdfLayout), sonst wie bisher laut
            // PdfDesignSettings (Default 'right').
            $logoOverride = $layout?->get('logo');
            if ($logoOverride !== null) {
                $logoPoint = $logoOverride->toPdfPoint($pageHeight);
                $writer->image($logoJpeg, $logoPoint['x'], $logoPoint['y'] - $logoBox[1], $logoBox[0], $logoBox[1]);
            } else {
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
        }

        // Fensterkuvert-Adresszone: NUR auf der ersten Seite.
        if (!$continuation && $companySettings !== null) {
            $this->drawWindowEnvelopeAddress($writer, $detail, $companySettings, $pageHeight, $layout);
        }

        // ENTSCHEIDUNG (Kollisionsvermeidung): siehe InvoicePdfGenerator —
        // identisches Muster. Titel/Kopfdatenblock beginnt auf Seite 1 erst
        // unterhalb der Fensterzone (y=90mm), auf Folgeseiten wie bisher.
        $y = $continuation
            ? $pageHeight - self::MARGIN
            : $pageHeight - (self::WINDOW_ZONE_BOTTOM_MM * self::MM_TO_PT) - 10.0;

        $titleOverride = !$continuation ? $layout?->get('title') : null;
        $titleX = self::MARGIN;
        if ($titleOverride !== null) {
            $titlePoint = $titleOverride->toPdfPoint($pageHeight);
            $titleX = $titlePoint['x'];
            $y = $titlePoint['y'];
        }
        $title = $continuation ? 'Angebot (Fortsetzung)' : 'Angebot';
        $titleFontSize = $titleOverride?->fontSize ?? 18.0;
        $writer->text($titleX, $y - 4, $title, $titleFontSize, true, color: $pdfDesignSettings?->accentColorPdfString() ?? '0 0 0');
        $y -= 30;

        if (!$continuation) {
            $infoLinesOverride = $layout?->get('info_lines');
            $infoLinesX = self::MARGIN;
            $infoLinesFontSize = 10.0;
            if ($infoLinesOverride !== null) {
                $infoPoint = $infoLinesOverride->toPdfPoint($pageHeight);
                $infoLinesX = $infoPoint['x'];
                $y = $infoPoint['y'];
                $infoLinesFontSize = $infoLinesOverride->fontSize ?? 10.0;
            }

            foreach ($this->buildOfferInfoLines($detail) as $line) {
                $writer->text($infoLinesX, $y, $line, $infoLinesFontSize);
                $y -= 14;
            }

            // Kein Seitenumbruch-Callback: der Einleitungstext wird (wie schon
            // vor der Rich-Text-Umstellung) nicht separat paginiert — bei sehr
            // langen Einleitungstexten kann er in die Positionstabelle laufen,
            // siehe RichTextPdfRenderer-Klassendoc.
            $introBlocks = $this->richTextRenderer->parse($offerPdfSettings?->introHtml);
            if ($introBlocks !== []) {
                $introOverride = $layout?->get('intro_text');
                $introX = self::MARGIN;
                $introWidth = $pageWidth - 2 * self::MARGIN;
                if ($introOverride !== null) {
                    $introPoint = $introOverride->toPdfPoint($pageHeight);
                    $introX = $introPoint['x'];
                    $y = $introPoint['y'];
                    $introWidth = $introOverride->widthPt() ?? $introWidth;
                } else {
                    $y -= 6;
                }
                $y = $this->richTextRenderer->render($writer, $introBlocks, $introX, $y, $introWidth, self::MARGIN + 60);
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
        // Vergebene, fortlaufende Angebotsnummer (Offer::$offerNumber) — zuvor
        // stand hier fälschlich ein Ausschnitt der internen ID.
        $lines[] = 'Angebotsnummer: ' . $offer->offerNumber;
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

        // Siehe InvoicePdfGenerator für die identische Begründung (Zeitraum
        // optional seit Migration 102).
        if ($offer->startDate !== null && $offer->endDate !== null) {
            $lines[] = 'Zeitraum: ' . $offer->startDate->format('d.m.Y') . ' - ' . $offer->endDate->format('d.m.Y');
        }

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
    private function drawWindowEnvelopeAddress(SimplePdfWriter $writer, OfferDetail $detail, CompanySettings $companySettings, float $pageHeight, ?PdfLayoutSettings $layout = null): void
    {
        $zoneX = self::WINDOW_ZONE_LEFT_MM * self::MM_TO_PT;
        $zoneTopY = $pageHeight - (self::WINDOW_ZONE_TOP_MM * self::MM_TO_PT);

        $returnLine = $this->buildReturnAddressLine($companySettings);
        $returnOverride = $layout?->get('return_address');
        $addressOverride = $layout?->get('customer_address');

        // Empfängeradresse: Standard direkt unter der Absenderzeile in der
        // DIN-5008-Fensterzone, per Layout-Editor überschreibbar.
        $addressX = $zoneX;
        $addressY = $zoneTopY - 25.0;
        $addressFontSize = 10.0;
        if ($addressOverride !== null) {
            $addressPoint = $addressOverride->toPdfPoint($pageHeight);
            $addressX = $addressPoint['x'];
            $addressY = $addressPoint['y'];
            $addressFontSize = $addressOverride->fontSize ?? 10.0;
        }

        // Einzeilige Absenderangabe (Rücksendeadresse) über der
        // Empfängeradresse. Nutzerhinweis "fehlt über der Kundenadresse":
        // wurde nur die Kundenadresse im Layout-Editor verschoben, wandert
        // die Absenderzeile jetzt mit (statt allein an der Standardposition
        // zurückzubleiben); eine eigene Position der Absenderzeile im
        // Layout-Editor hat weiterhin Vorrang. Dünne Unterstreichung wie bei
        // DIN-5008-Rücksendeangaben üblich.
        $returnFontSize = $returnOverride?->fontSize ?? 7.5;
        if ($returnOverride !== null) {
            $returnPoint = $returnOverride->toPdfPoint($pageHeight);
            $returnX = $returnPoint['x'];
            $returnY = $returnPoint['y'];
        } else {
            $returnX = $addressX;
            $returnY = $addressY + 18.0;
        }
        if ($returnLine !== null) {
            $writer->text($returnX, $returnY, $returnLine, $returnFontSize);
            $writer->line($returnX, $returnY - 2.0, $returnX + $writer->textWidth($returnLine, $returnFontSize), $returnY - 2.0, 0.4, '0.3 0.3 0.3');
        }

        $y = $addressY;

        foreach ($this->buildCustomerAddressLines($detail->customer, $detail->contact) as $line) {
            $writer->text($addressX, $y, $line, $addressFontSize);
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

    /**
     * Ansprechpartner-Auswahl (Migration 104): siehe
     * InvoicePdfGenerator::buildCustomerAddressLines() für die identische
     * Begründung/Semantik.
     *
     * @return string[]
     */
    private function buildCustomerAddressLines(?Customer $customer, ?CustomerContact $contact = null): array
    {
        if ($customer === null) {
            return ['Noch nicht zugeordnet'];
        }

        $lines = [$customer->displayName()];

        $useContactAddress = $contact !== null && $contact->hasOwnAddress();
        if ($contact !== null) {
            $lines[] = $contact->name;
        }

        $addressLine2 = $useContactAddress ? $contact->addressLine2 : $customer->addressLine2;
        $street = $useContactAddress ? $contact->street : $customer->street;
        $postalCode = $useContactAddress ? $contact->postalCode : $customer->postalCode;
        $city = $useContactAddress ? $contact->city : $customer->city;

        if ($addressLine2 !== null && trim($addressLine2) !== '') {
            $lines[] = trim($addressLine2);
        }
        if ($street !== null && trim($street) !== '') {
            $lines[] = trim($street);
        }
        $cityLine = trim(($postalCode ?? '') . ' ' . ($city ?? ''));
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

    /** @param string[] $labels Spaltenüberschriften: Bezeichnung, Menge, Einzelpreis, Summe */
    private function drawTableHeader(SimplePdfWriter $writer, float $y, float $pageWidth, ?PdfDesignSettings $pdfDesignSettings = null, array $labels = ['Artikel', 'Menge', 'Einzelpreis', 'Summe']): float
    {
        $columns = $this->columnLayout();

        $writer->filledRect(self::MARGIN, $y - 14, $pageWidth - 2 * self::MARGIN, 18, $pdfDesignSettings?->accentColorTintPdfString() ?? '0.92 0.92 0.92');
        $writer->text($columns['article']['x'] + 2, $y - 8, $labels[0], 9, true);
        $writer->text($columns['quantity']['x'] + 2, $y - 8, $labels[1], 9, true);
        // Preisspalten rechtsbündig (Nutzerwunsch), Überschriften passend dazu.
        $writer->textRight($this->columnRight($columns['unitPrice']), $y - 8, $labels[2], 9, true);
        $writer->textRight($this->columnRight($columns['lineTotal']), $y - 8, $labels[3], 9, true);

        return $y - 22;
    }

    /** @param array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool} $row */
    private function drawRow(SimplePdfWriter $writer, float $topY, array $row): void
    {
        $columns = $this->columnLayout();
        $baseline = $topY - self::TABLE_LINE_HEIGHT + 2;

        ArticlePdfRow::drawNameColumn($writer, $columns['article']['x'] + 2, $topY, $row, self::TABLE_FONT_SIZE, self::TABLE_LINE_HEIGHT, $row['isSection']);

        if (!$row['isSection']) {
            $writer->text($columns['quantity']['x'] + 2, $baseline, $row['quantity'], self::TABLE_FONT_SIZE);
            $writer->textRight($this->columnRight($columns['unitPrice']), $baseline, $row['unitPrice'], self::TABLE_FONT_SIZE);
            $writer->textRight($this->columnRight($columns['lineTotal']), $baseline, $row['lineTotal'], self::TABLE_FONT_SIZE);
        }
    }

    private ?ArticlePdfRow $articleRow = null;

    private function articleRow(): ArticlePdfRow
    {
        return $this->articleRow ??= new ArticlePdfRow($this->fileStorage);
    }

    /** @param array{x: float, width: float} $column */
    private function columnRight(array $column): float
    {
        return $column['x'] + $column['width'] - 2;
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . $this->currency();
    }

    /**
     * Summenzeile unterhalb der Tabellen: Bezeichnung links im Summenblock,
     * Betrag rechtsbündig an derselben Kante wie die "Summe"-Spalte.
     */
    private function drawSummaryLine(SimplePdfWriter $writer, float $pageWidth, float $y, string $label, string $amount, float $fontSize = 9, bool $bold = false): void
    {
        $writer->text($pageWidth - self::MARGIN - 230, $y, $label, $fontSize, $bold);
        $writer->textRight($this->columnRight($this->columnLayout()['lineTotal']), $y, $amount, $fontSize, $bold);
    }

    /**
     * Zeichnet eine zusätzliche Tabelle (Arbeitszeit, Verkaufspositionen)
     * mit Überschrift und Tabellenkopf im selben Raster wie die
     * Artikeltabelle; bei Seitenumbruch wird der Tabellenkopf wiederholt.
     *
     * @param string[] $labels
     * @param array<int, array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}> $rows
     * @param callable(): float $newPage
     */
    private function drawTableSection(SimplePdfWriter $writer, float $y, float $pageWidth, string $title, array $labels, array $rows, callable $newPage, ?PdfDesignSettings $pdfDesignSettings): float
    {
        $y -= 16;
        $firstRowHeight = ArticlePdfRow::contentHeight($rows[0], self::TABLE_LINE_HEIGHT) + self::TABLE_ROW_PADDING;
        if ($y - 36 - $firstRowHeight < self::MARGIN + 60) {
            $y = $newPage();
        }

        $writer->text(self::MARGIN, $y, $title, 10, true);
        $y -= 8;
        $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings, $labels);

        foreach ($rows as $row) {
            $rowHeight = ArticlePdfRow::contentHeight($row, self::TABLE_LINE_HEIGHT) + self::TABLE_ROW_PADDING;
            if ($y - $rowHeight < self::MARGIN + 60) {
                $y = $newPage();
                $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings, $labels);
            }
            $this->drawRow($writer, $y, $row);
            $y -= $rowHeight;
        }

        return $y;
    }

    /** @return array<int, array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}> */
    private function buildLaborRows(OfferDetail $detail): array
    {
        $columns = $this->columnLayout();
        $rows = [];
        foreach ($detail->laborItems as $laborEntry) {
            $name = $laborEntry->laborTimeArticle->name
                . ($laborEntry->user !== null ? ' – ' . $laborEntry->user->displayName() : '');
            $rows[] = [
                'nameLines' => $this->wrapText($name, $columns['article']['width'], self::TABLE_FONT_SIZE),
                'quantity' => number_format((float) $laborEntry->item->hours, 2, ',', '.') . ' ' . $laborEntry->item->billingUnit->shortLabel(),
                'unitPrice' => $this->formatAmount((float) $laborEntry->item->unitRatePerHour),
                'lineTotal' => $this->formatAmount($laborEntry->lineTotal()),
                'isSection' => false,
            ];
        }

        return $rows;
    }

    /** @return array<int, array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}> */
    private function buildSaleRows(OfferDetail $detail): array
    {
        $columns = $this->columnLayout();
        $rows = [];
        foreach ($detail->saleItems as $saleEntry) {
            $nameColumn = $this->articleRow()->nameColumn(
                $saleEntry->article,
                [$saleEntry->displayName()],
                $columns['article']['width'],
                self::TABLE_FONT_SIZE,
                fn (string $text, float $width, float $fontSize): array => $this->wrapText($text, $width, $fontSize),
            );
            $rows[] = [
                'nameLines' => $nameColumn['nameLines'],
                'shortTextLines' => $nameColumn['shortTextLines'],
                'image' => $nameColumn['image'],
                'quantity' => (string) $saleEntry->item->quantity,
                'unitPrice' => $this->formatAmount((float) $saleEntry->item->unitPrice),
                'lineTotal' => $this->formatAmount($saleEntry->lineTotal()),
                'isSection' => false,
            ];
        }

        return $rows;
    }

    private function drawTerms(SimplePdfWriter $writer, OfferDetail $detail, string $termsText, float $y, float $pageWidth, float $pageHeight, int $pageNumber, ?PdfDesignSettings $pdfDesignSettings = null): float
    {
        $writer->text(self::MARGIN, $y, 'Vermietbedingungen', 11, true);
        $y -= 18;

        $lines = $this->wrapText($termsText, $pageWidth - 2 * self::MARGIN, 8.0, true);

        foreach ($lines as $line) {
            if ($y < self::CONTENT_BOTTOM) {
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

    /** Währungsangabe hinter Beträgen (Einstellungen › PDF-Design, Migration 110). */
    private function currency(): string
    {
        return $this->amountFormatter?->suffix() ?? ' EUR';
    }
}
