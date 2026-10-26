<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\Invoice\Query\InvoiceDetail;
use App\Application\Invoice\Query\InvoiceRow;
use App\Domain\Customer\Customer;
use App\Domain\Invoice\InvoiceFreeTextItem;
use App\Domain\Invoice\InvoiceSectionText;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\InvoicePdfSettings;
use App\Domain\Settings\InvoiceSettings;
use App\Domain\Settings\LegalSettings;
use App\Domain\Settings\PdfDesignSettings;
use App\Domain\Settings\PdfLayout\PdfLayoutSettings;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Erzeugt eine Rechnung als PDF — strukturell eng an OfferPdfGenerator
 * angelehnt (gleiche Low-Level-Schreibmechanik SimplePdfWriter, gleiche
 * Seitenumbruch-/Fortsetzungsseiten-Logik, gleiche Intro-/Schlusstext-
 * Einfügepunkte über InvoicePdfSettings). Abweichungen gegenüber dem
 * Angebots-PDF:
 *  - Titel "Rechnung" ("Rechnung (Fortsetzung)" auf Folgeseiten).
 *  - Kopfdatenblock zeigt Rechnungsnummer/-datum, Fälligkeitsdatum
 *    (inkl. Zahlungsziel-Hinweis), Kunde, Ort, Leistungszeitraum,
 *    Anmerkung und optional "Ihre Referenz" (buyerReference, BT-10).
 *  - Ist die Rechnung noch nicht finalisiert (Entwurf, invoiceNumber noch
 *    null), wird "ENTWURF – noch keine Rechnungsnummer vergeben" deutlich
 *    sichtbar ausgegeben, damit ein heruntergeladenes Entwurfs-PDF niemals
 *    mit einer echten Rechnung verwechselt werden kann.
 *  - Summenblock: bei Kleinunternehmer (Snapshot, oder — solange die
 *    Rechnung noch Entwurf/nicht finalisiert ist — der AKTUELLEN
 *    InvoiceSettings als Vorschau) nur "Gesamtbetrag:" plus Pflichthinweis
 *    nach § 19 UStG; sonst Netto-/USt-/Bruttosumme als drei Zeilen.
 *  - Optionaler Bankverbindungs-Block (InvoiceSettings), sofern mindestens
 *    IBAN/BIC/Bankname gepflegt sind.
 *  - Steuernummer/USt-IdNr. (InvoiceSettings, NICHT CompanySettings) im
 *    Kopfbereich als Kleingedrucktes.
 *
 * Einleitungs-/Schlusstext (InvoicePdfSettings::$introHtml/$closingHtml)
 * sind Rich-Text-HTML, siehe OfferPdfGenerator-Klassendoc für die
 * identische Begründung (RichTextPdfRenderer statt reiner Text-Ausgabe).
 */
final class InvoicePdfGenerator
{
    private const MARGIN = 40.0;
    private const LOGO_MAX_WIDTH = 120.0;
    private const LOGO_MAX_HEIGHT = 50.0;
    private const TABLE_FONT_SIZE = 8.5;
    private const TABLE_LINE_HEIGHT = 11.0;
    private const TABLE_ROW_PADDING = 6.0;

    // DIN 5008 (Faltmarken + Fensterkuvert-Adresszone), siehe Klassen-Doc.
    private const MM_TO_PT = 2.83465;
    private const FOLD_MARK_LENGTH_MM = 4.0;
    private const FOLD_MARK_AT_MM = [105.0, 210.0];
    private const WINDOW_ZONE_LEFT_MM = 20.0;
    private const WINDOW_ZONE_TOP_MM = 45.0;
    private const WINDOW_ZONE_WIDTH_MM = 85.0;
    private const WINDOW_ZONE_BOTTOM_MM = 90.0; // TOP_MM + Höhe (45mm)

    public function __construct(
        private readonly LocalFileStorage $fileStorage,
        private readonly RichTextPdfRenderer $richTextRenderer,
    ) {
    }

    public function generate(
        InvoiceDetail $detail,
        CompanySettings $companySettings,
        InvoiceSettings $invoiceSettings,
        ?LegalSettings $legalSettings = null,
        ?InvoicePdfSettings $invoicePdfSettings = null,
        ?PdfDesignSettings $pdfDesignSettings = null,
        ?PdfLayoutSettings $layout = null
    ): string {
        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();

        $logoJpeg = $this->prepareLogoJpeg($companySettings);
        $logoBox = $logoJpeg !== null ? $this->fitBox($logoJpeg, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT) : null;

        $rows = $this->buildRows($detail);
        $pageNumber = 1;

        $writer->addPage();
        $y = $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, $logoJpeg, $logoBox, $pageWidth, $pageHeight, $pageNumber, continuation: false, invoicePdfSettings: $invoicePdfSettings, pdfDesignSettings: $pdfDesignSettings, layout: $layout);
        $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings);

        if ($rows === []) {
            $writer->text(self::MARGIN, $y - self::TABLE_LINE_HEIGHT, 'Keine Positionen erfasst.', 10);
            $y -= self::TABLE_LINE_HEIGHT + self::TABLE_ROW_PADDING;
        }

        foreach ($rows as $row) {
            $rowLineCount = max(count($row['nameLines']), 1);
            $rowHeight = $rowLineCount * self::TABLE_LINE_HEIGHT + self::TABLE_ROW_PADDING;

            if ($y - $rowHeight < self::MARGIN + 90) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings);
            }

            $this->drawRow($writer, $y, $row);
            $y -= $rowHeight;
        }

        // Reservierter Platz für den Summenblock: Zwischensumme + ggf.
        // Zubehörpauschale-/Kombirabatt-/Versandkosten-Zeile + Netto/USt/Brutto
        // (bis zu 7 Zeilen a 14-16pt) — etwas großzügiger als vorher (90pt),
        // da bis zu drei zusätzliche Zeilen (Zubehörpauschale, Kombirabatt,
        // Versandkosten) hinzukommen.
        if ($y - 134 < self::MARGIN + 90) {
            $pageNumber++;
            $writer->addPage();
            $y = $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
        }

        $isSmallBusiness = $detail->invoice->isSmallBusiness ?? $invoiceSettings->isSmallBusiness;
        $vatRatePercent = $detail->invoice->vatRatePercent ?? $invoiceSettings->vatRatePercent;

        // Zentrale Summenberechnung (Mietpreise -> Zubehörpauschale ->
        // Kombirabatt -> Netto/USt/Brutto), siehe App\Domain\Pricing\DocumentTotals
        // — ersetzt die vormals hier duplizierte Ad-hoc-Berechnung.
        $totals = $detail->totals($invoiceSettings);

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
        $y -= 20;

        // Pauschalpreis (siehe Invoice::$flatRateEnabled-Klassendoc): Label
        // wechselt auf "Pauschale für Miete", der Betrag ist bereits
        // $totals->rentalNetTotal (InvoiceDetail::totals() setzt dort den
        // Pauschalbetrag statt der tatsächlichen Positionssumme ein).
        $writer->text($pageWidth - self::MARGIN - 230, $y, ($detail->invoice->flatRateEnabled ? 'Pauschale für Miete:' : 'Zwischensumme Mietpreise:'), 9);
        $writer->text($pageWidth - self::MARGIN - 75, $y, number_format($totals->rentalNetTotal, 2, ',', '.') . ' EUR', 9);
        $y -= 14;

        // Verkaufs-/Sonstige Positionen (InvoiceItem::$isRentalItem = false)
        // — bleiben beim Pauschalpreis unberührt, eigene Zeile wie Freitext-
        // Posten/Arbeitszeit (siehe DocumentTotals::$saleTotal).
        if ($detail->saleItemsTotal() > 0) {
            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Verkauf/Sonstiges:', 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format($detail->saleItemsTotal(), 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        if ($detail->invoice->accessoryFlatRateEnabled && $detail->invoice->accessoryFlatRatePercent !== null) {
            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Zubehörpauschale (' . number_format((float) $detail->invoice->accessoryFlatRatePercent, 2, ',', '.') . '%):', 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format($totals->accessoryFlatRateAmount, 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        if ($detail->invoice->discountType !== null && $detail->invoice->discountValue !== null) {
            $discountLabel = $detail->invoice->discountType === 'percent'
                ? 'Kombirabatt (' . number_format((float) $detail->invoice->discountValue, 2, ',', '.') . '%):'
                : 'Kombirabatt (Festbetrag):';
            $writer->text($pageWidth - self::MARGIN - 230, $y, $discountLabel, 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '-' . number_format($totals->discountAmount, 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        foreach ($detail->laborItems as $laborEntry) {
            if ($y - 14 < self::MARGIN + 90) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                $y -= 20;
            }

            $laborLabel = $laborEntry->laborTimeArticle->name . ' ('
                . number_format((float) $laborEntry->item->hours, 2, ',', '.') . ' Std. à '
                . number_format((float) $laborEntry->item->unitRatePerHour, 2, ',', '.') . ' EUR):';
            $writer->text($pageWidth - self::MARGIN - 230, $y, $laborLabel, 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format($laborEntry->lineTotal(), 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        // Freitext-Posten (orderedRows()-Konzept): erscheinen in der Tabelle
        // oben zusammen mit den Mietpositionen, hier im Summenblock aber wie
        // Arbeitszeit-Positionen als eigene Zeile NACH dem Kombirabatt
        // (siehe DocumentTotals::$freeTextTotal).
        if ($detail->freeTextTotal() > 0) {
            if ($y - 14 < self::MARGIN + 90) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                $y -= 20;
            }

            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Freitext-Posten:', 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format($detail->freeTextTotal(), 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        if ($detail->invoice->shippingAmount !== null) {
            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Versandkosten:', 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format((float) $detail->invoice->shippingAmount, 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        if ($isSmallBusiness) {
            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Gesamtbetrag:', 10, true);
            $writer->text($pageWidth - self::MARGIN - 75, $y, number_format($totals->grossTotal, 2, ',', '.') . ' EUR', 10, true);
            $y -= 16;
            $writer->text(self::MARGIN, $y, 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.', 8.5);
            $y -= 24;
        } else {
            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Nettosumme:', 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, number_format($totals->netTotal, 2, ',', '.') . ' EUR', 9);
            $y -= 14;
            $writer->text($pageWidth - self::MARGIN - 230, $y, 'zzgl. ' . number_format((float) $vatRatePercent, 2, ',', '.') . '% USt:', 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, number_format($totals->taxAmount, 2, ',', '.') . ' EUR', 9);
            $y -= 16;
            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Gesamtbetrag (brutto):', 10, true);
            $writer->text($pageWidth - self::MARGIN - 75, $y, number_format($totals->grossTotal, 2, ',', '.') . ' EUR', 10, true);
            $y -= 30;
        }

        $bankLine = $this->buildBankLine($invoiceSettings);
        if ($bankLine !== null) {
            if ($y < self::MARGIN + 30) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
            }
            $writer->text(self::MARGIN, $y, $bankLine, 8.5);
            $y -= 22;
        }

        $closingBlocks = $this->richTextRenderer->parse($invoicePdfSettings?->closingHtml);
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
                self::MARGIN + 20,
                function () use ($writer, $detail, $companySettings, $invoiceSettings, $pageWidth, $pageHeight, $pdfDesignSettings, &$closingPageNumber): float {
                    $closingPageNumber++;
                    $writer->addPage();

                    return $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $closingPageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                }
            );
        }

        // Dokumenteigener Schlusstext (Invoice::$closingText, Kundenwunsch
        // "Schlusstext" — siehe Aufgabenstellung), bewusst NACH dem globalen
        // Schlusstext (InvoicePdfSettings::$closingHtml, oben) gerendert:
        // Letzterer ist ein für ALLE Rechnungen fester Textbaustein, dieser
        // hier eine individuelle Ergänzung je Rechnung, daher als
        // "letztes Wort" direkt vor den Vermietbedingungen platziert.
        // Identisches Rendering-Muster wie der globale Schlusstext oben
        // (RichTextPdfRenderer, Seitenumbruch-Callback mit neu gezeichnetem
        // Kopfbereich).
        $documentClosingBlocks = $this->richTextRenderer->parse($detail->invoice->closingText);
        if ($documentClosingBlocks !== []) {
            $documentClosingPageNumber = $pageNumber;
            $y = $this->richTextRenderer->render(
                $writer,
                $documentClosingBlocks,
                self::MARGIN,
                $y - 10.0,
                $pageWidth - 2 * self::MARGIN,
                self::MARGIN + 20,
                function () use ($writer, $detail, $companySettings, $invoiceSettings, $pageWidth, $pageHeight, $pdfDesignSettings, &$documentClosingPageNumber): float {
                    $documentClosingPageNumber++;
                    $writer->addPage();

                    return $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $documentClosingPageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                }
            );
            $pageNumber = $documentClosingPageNumber;
        }

        $termsText = $this->plainTextFromMarkdown($legalSettings?->rentalTermsMarkdown);
        if ($termsText !== null) {
            $y = $this->drawTerms($writer, $detail, $companySettings, $invoiceSettings, $termsText, $y, $pageWidth, $pageHeight, $pageNumber, $pdfDesignSettings);
        }

        return $writer->output();
    }

    /** @return array<int, array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}> */
    private function buildRows(InvoiceDetail $detail): array
    {
        $columns = $this->columnLayout();
        $rows = [];

        foreach ($detail->orderedRows() as $row) {
            if ($row->type === InvoiceRow::TYPE_SECTION) {
                /** @var InvoiceSectionText $sectionText */
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

            if ($row->type === InvoiceRow::TYPE_FREE_TEXT) {
                /** @var InvoiceFreeTextItem $item */
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
            // Pauschalpreis (siehe Invoice::$flatRateEnabled-Klassendoc):
            // bei Mietpositionen bleiben Menge/Bezeichnung sichtbar, aber
            // Einzelpreis/Summe werden nicht genannt. Verkaufs-/Sonstige
            // Positionen (isRentalItem = false) sind davon unberührt.
            $hidePrice = $detail->invoice->flatRateEnabled && $entry->item->isRentalItem;
            $rows[] = [
                'nameLines' => $this->wrapText($entry->displayName(), $columns['article']['width'], self::TABLE_FONT_SIZE),
                'quantity' => (string) $entry->item->quantity,
                'unitPrice' => $hidePrice ? '–' : (number_format((float) $entry->item->unitPricePerDay, 2, ',', '.') . ' EUR'),
                'lineTotal' => $hidePrice ? '–' : (number_format($entry->lineTotal(), 2, ',', '.') . ' EUR'),
                'isSection' => false,
            ];
        }

        return $rows;
    }

    private function drawHeader(
        SimplePdfWriter $writer,
        InvoiceDetail $detail,
        CompanySettings $companySettings,
        InvoiceSettings $invoiceSettings,
        ?string $logoJpeg,
        ?array $logoBox,
        float $pageWidth,
        float $pageHeight,
        int $pageNumber,
        bool $continuation = false,
        ?InvoicePdfSettings $invoicePdfSettings = null,
        ?PdfDesignSettings $pdfDesignSettings = null,
        ?PdfLayoutSettings $layout = null
    ): float {
        // Faltmarken (DIN 5008) werden auf JEDER Seite gezeichnet, da das
        // Falzen unabhängig vom Inhalt ist (siehe Klassen-Doc).
        $this->drawFoldMarks($writer, $pageHeight);

        if ($logoJpeg !== null && $logoBox !== null) {
            // Logo-Position: per visuellem Editor überschreibbar (siehe
            // App\Domain\Settings\PdfLayout), sonst wie bisher links/rechts
            // oben laut PdfDesignSettings (keine Kollision mit der
            // Fensterkuvert-Adresszone).
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

        // Fensterkuvert-Adresszone: NUR auf der ersten Seite (kein
        // Kuvertfenster für Folgeseiten relevant, siehe Klassen-Doc).
        if (!$continuation) {
            $this->drawWindowEnvelopeAddress($writer, $detail, $companySettings, $pageHeight, $layout);
        }

        // ENTSCHEIDUNG (Kollisionsvermeidung Fensterkuvert-Zone vs.
        // Kopfdatenblock): die Fensterzone (y=45-90mm von oben, linke
        // Seite) hat Vorrang — auf Seite 1 beginnt der Titel/Kopfdatenblock
        // daher erst UNTERHALB von y=90mm; auf Folgeseiten (kein
        // Kuvertfenster relevant) wie bisher direkt unter dem oberen Rand.
        // Das Logo (oben rechts) bleibt unverändert, da es die linke
        // Fensterzone horizontal nicht überschneidet.
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
        $title = $continuation ? 'Rechnung (Fortsetzung)' : 'Rechnung';
        $titleFontSize = $titleOverride?->fontSize ?? 18.0;
        $writer->text($titleX, $y - 4, $title, $titleFontSize, true, color: $pdfDesignSettings?->accentColorPdfString() ?? '0 0 0');
        $y -= 24;

        if (!$detail->invoice->isFinalized()) {
            $writer->text(self::MARGIN, $y, 'ENTWURF – noch keine Rechnungsnummer vergeben', 10, true);
            $y -= 16;
        } else {
            $y -= 6;
        }

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

            foreach ($this->buildInvoiceInfoLines($detail, $invoiceSettings) as $line) {
                $writer->text($infoLinesX, $y, $line, $infoLinesFontSize);
                $y -= 14;
            }

            $taxIdLine = $this->buildTaxIdLine($invoiceSettings);
            if ($taxIdLine !== null) {
                $writer->text($infoLinesX, $y, $taxIdLine, 7.5);
                $y -= 12;
            }

            // Kein Seitenumbruch-Callback für den Einleitungstext, siehe
            // OfferPdfGenerator für die identische Begründung.
            $introBlocks = $this->richTextRenderer->parse($invoicePdfSettings?->introHtml);
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
    private function buildInvoiceInfoLines(InvoiceDetail $detail, InvoiceSettings $invoiceSettings): array
    {
        $invoice = $detail->invoice;
        $lines = [];

        $lines[] = 'Rechnungsnummer: ' . ($invoice->invoiceNumber ?? '—');
        $lines[] = 'Rechnungsdatum: ' . ($invoice->invoiceDate?->format('d.m.Y') ?? $invoice->createdAt->format('d.m.Y') . ' (Entwurf)');

        if ($invoice->dueDate !== null) {
            $lines[] = 'Fällig am: ' . $invoice->dueDate->format('d.m.Y') . ' — bitte überweisen Sie den Betrag bis zu diesem Datum.';
        } else {
            $lines[] = 'Zahlungsziel: ' . $invoiceSettings->paymentTermDays . ' Tage nach Rechnungsstellung (voraussichtlich, noch nicht versendet).';
        }

        // Die vormalige Zeile "Kunde: ..." entfällt hier — der Kunde steht
        // jetzt im Adressblock der Fensterkuvert-Zone (siehe
        // drawWindowEnvelopeAddress()).

        if ($invoice->eventLocation !== null && trim($invoice->eventLocation) !== '') {
            $lines[] = 'Ort: ' . $invoice->eventLocation;
        }

        $lines[] = 'Leistungszeitraum: ' . $invoice->startDate->format('d.m.Y') . ' - ' . $invoice->endDate->format('d.m.Y');

        if ($invoice->buyerReference !== null && trim($invoice->buyerReference) !== '') {
            $lines[] = 'Ihre Referenz: ' . $invoice->buyerReference;
        }

        if ($invoice->note !== null && trim($invoice->note) !== '') {
            $lines[] = 'Anmerkung: ' . $invoice->note;
        }

        return $lines;
    }

    /**
     * Zwei kurze waagerechte Faltmarken (DIN 5008) am linken Seitenrand,
     * gemessen von OBEN bei 105mm und 210mm (Drittelung eines A4-Blatts für
     * ein C6/5-Kuvert). Wird auf JEDER Seite gezeichnet (siehe Aufrufer).
     */
    private function drawFoldMarks(SimplePdfWriter $writer, float $pageHeight): void
    {
        foreach (self::FOLD_MARK_AT_MM as $mmFromTop) {
            $y = $pageHeight - ($mmFromTop * self::MM_TO_PT);
            $writer->line(0.0, $y, self::FOLD_MARK_LENGTH_MM * self::MM_TO_PT, $y, 0.5, '0.5 0.5 0.5');
        }
    }

    /**
     * Fensterkuvert-Adresszone (DIN 5008): x=20-105mm, y=45-90mm von oben.
     * Ganz oben im Fenster (kleine Schrift) die einzeilige
     * Rücksendeangabe (Firmenadresse), darunter der vollständige
     * Kundenadressblock. Bei fehlenden Adressdaten wird die jeweilige
     * Zeile/der jeweilige Teil einfach weggelassen statt abzustürzen.
     */
    private function drawWindowEnvelopeAddress(SimplePdfWriter $writer, InvoiceDetail $detail, CompanySettings $companySettings, float $pageHeight, ?PdfLayoutSettings $layout = null): void
    {
        $zoneX = self::WINDOW_ZONE_LEFT_MM * self::MM_TO_PT;
        $zoneTopY = $pageHeight - (self::WINDOW_ZONE_TOP_MM * self::MM_TO_PT);

        $returnLine = $this->buildReturnAddressLine($companySettings);
        $returnOverride = $layout?->get('return_address');
        $returnX = $zoneX;
        $returnFontSize = 7.5;
        $y = $zoneTopY - 7.0;
        if ($returnOverride !== null) {
            $returnPoint = $returnOverride->toPdfPoint($pageHeight);
            $returnX = $returnPoint['x'];
            $y = $returnPoint['y'];
            $returnFontSize = $returnOverride->fontSize ?? 7.5;
        }
        if ($returnLine !== null) {
            $writer->text($returnX, $y, $returnLine, $returnFontSize);
        }
        $y -= 18.0;

        $addressOverride = $layout?->get('customer_address');
        $addressX = $zoneX;
        $addressFontSize = 10.0;
        if ($addressOverride !== null) {
            $addressPoint = $addressOverride->toPdfPoint($pageHeight);
            $addressX = $addressPoint['x'];
            $y = $addressPoint['y'];
            $addressFontSize = $addressOverride->fontSize ?? 10.0;
        }

        foreach ($this->buildCustomerAddressLines($detail->customer) as $line) {
            $writer->text($addressX, $y, $line, $addressFontSize);
            $y -= 12.5;
        }
    }

    /** Einzeilige Rücksendeangabe "Firma, Straße, PLZ Ort" — fehlende Teile werden übersprungen, NULL bei komplett fehlender Adresse. */
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

    private function buildTaxIdLine(InvoiceSettings $invoiceSettings): ?string
    {
        $parts = [];
        if ($invoiceSettings->taxNumber !== null && trim($invoiceSettings->taxNumber) !== '') {
            $parts[] = 'Steuernummer: ' . $invoiceSettings->taxNumber;
        }
        if ($invoiceSettings->vatId !== null && trim($invoiceSettings->vatId) !== '') {
            $parts[] = 'USt-IdNr.: ' . $invoiceSettings->vatId;
        }

        return $parts === [] ? null : implode('   ', $parts);
    }

    private function buildBankLine(InvoiceSettings $invoiceSettings): ?string
    {
        if (
            ($invoiceSettings->bankName === null || trim($invoiceSettings->bankName) === '')
            && ($invoiceSettings->iban === null || trim($invoiceSettings->iban) === '')
            && ($invoiceSettings->bic === null || trim($invoiceSettings->bic) === '')
        ) {
            return null;
        }

        $parts = [];
        if ($invoiceSettings->bankName !== null && trim($invoiceSettings->bankName) !== '') {
            $parts[] = $invoiceSettings->bankName;
        }
        if ($invoiceSettings->iban !== null && trim($invoiceSettings->iban) !== '') {
            $parts[] = 'IBAN ' . $invoiceSettings->iban;
        }
        if ($invoiceSettings->bic !== null && trim($invoiceSettings->bic) !== '') {
            $parts[] = 'BIC ' . $invoiceSettings->bic;
        }

        return 'Bankverbindung: ' . implode(', ', $parts);
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

    private function drawTerms(SimplePdfWriter $writer, InvoiceDetail $detail, CompanySettings $companySettings, InvoiceSettings $invoiceSettings, string $termsText, float $y, float $pageWidth, float $pageHeight, int $pageNumber, ?PdfDesignSettings $pdfDesignSettings = null): float
    {
        $writer->text(self::MARGIN, $y, 'Vermietbedingungen', 11, true);
        $y -= 18;

        $lines = $this->wrapText($termsText, $pageWidth - 2 * self::MARGIN, 8.0, true);

        foreach ($lines as $line) {
            if ($y < self::MARGIN + 20) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
            }
            $writer->text(self::MARGIN, $y, $line, 8.0);
            $y -= 11.0;
        }

        return $y;
    }

    /**
     * Ausschließlich noch für LegalSettings::$rentalTermsMarkdown verwendet,
     * siehe OfferPdfGenerator-Klassendoc. Reduziert Markdown grob auf reinen
     * Text fürs PDF, siehe OfferPdfGenerator::plainTextFromMarkdown() (bewusst identisch
     * dupliziert statt geteilt, gleiche Begründung wie dort).
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
