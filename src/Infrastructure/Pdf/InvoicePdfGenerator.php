<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\Invoice\Query\InvoiceDetail;
use App\Application\Invoice\Query\InvoiceRow;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerContact;
use App\Domain\Invoice\InvoiceFreeTextItem;
use App\Domain\Invoice\InvoiceSectionText;
use App\Domain\Settings\BankAccount;
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
    // Unterste Grenze für Inhalt (Summen, Texte, Bedingungen) — darunter
    // liegt die Fußzeile (siehe DocumentFooter::TOP_Y).
    private const CONTENT_BOTTOM = DocumentFooter::TOP_Y + 12.0;
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
        private readonly ?DocumentFooter $footer = null,
        private readonly ?PdfAmountFormatter $amountFormatter = null,
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
            $rowHeight = ArticlePdfRow::contentHeight($row, self::TABLE_LINE_HEIGHT) + self::TABLE_ROW_PADDING;

            if ($y - $rowHeight < self::MARGIN + 90) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings);
            }

            $this->drawRow($writer, $y, $row);
            $y -= $rowHeight;
        }

        // Seitenumbruch-Helfer für den Summen-/Tabellenbereich unterhalb
        // der Positionsliste (Folgeseite mit verkürztem Kopf).
        $newPage = function () use ($writer, $detail, $companySettings, $invoiceSettings, $pageWidth, $pageHeight, $pdfDesignSettings, &$pageNumber): float {
            $pageNumber++;
            $writer->addPage();

            return $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings) - 20;
        };

        // Reservierter Platz für den ersten Teil des Summenblocks
        // (Zwischensumme, Verkauf, Zubehörpauschale, Kombirabatt).
        if ($y - 90 < self::MARGIN + 90) {
            $y = $newPage() + 20;
        }

        $isSmallBusiness = $detail->invoice->isSmallBusiness ?? $invoiceSettings->isSmallBusiness;
        $vatRatePercent = $detail->invoice->vatRatePercent ?? $invoiceSettings->vatRatePercent;

        // Zentrale Summenberechnung (Mietpreise -> Zubehörpauschale ->
        // Kombirabatt -> Netto/USt/Brutto), siehe App\Domain\Pricing\DocumentTotals.
        $totals = $detail->totals($invoiceSettings);

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
        $y -= 20;

        // Nutzerwunsch: EINE "Zwischensumme" über alle Positionen der
        // Tabelle oben — Mietpositionen UND Freitext-Posten (die ohnehin
        // gemeinsam in die Basis für Zubehörpauschale/Kombirabatt eingehen,
        // siehe DocumentTotals::calculate()); keine eigene
        // "Freitext-Posten"-Zeile mehr. Beim Pauschalpreis (siehe
        // Invoice::$flatRateEnabled) ersetzt die Pauschale die Summe der
        // Mietpositionen — sie wird dann vorab ausgewiesen, damit die
        // Zwischensumme nachvollziehbar bleibt.
        if ($detail->invoice->flatRateEnabled) {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Pauschale für Miete:', $this->formatAmount($totals->rentalNetTotal));
            $y -= 14;
        }
        if (!$detail->invoice->flatRateEnabled || $totals->freeTextTotal > 0) {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Zwischensumme:', $this->formatAmount($totals->rentalNetTotal + $totals->freeTextTotal));
            $y -= 14;
        }

        // Verkaufs-/Sonstige Positionen (InvoiceItem::$isRentalItem = false)
        // — bleiben beim Pauschalpreis unberührt (siehe DocumentTotals::$saleTotal).
        if ($detail->saleItemsTotal() > 0) {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Verkauf/Sonstiges:', '+' . $this->formatAmount($detail->saleItemsTotal()));
            $y -= 14;
        }

        if ($detail->invoice->accessoryFlatRateEnabled && $detail->invoice->accessoryFlatRatePercent !== null) {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Zubehörpauschale (' . number_format((float) $detail->invoice->accessoryFlatRatePercent, 2, ',', '.') . '%):', '+' . $this->formatAmount($totals->accessoryFlatRateAmount));
            $y -= 14;
        }

        if ($detail->invoice->discountType !== null && $detail->invoice->discountValue !== null) {
            $discountLabel = $detail->invoice->discountType === 'percent'
                ? 'Kombirabatt (' . number_format((float) $detail->invoice->discountValue, 2, ',', '.') . '%):'
                : 'Kombirabatt (Festbetrag):';
            $this->drawSummaryLine($writer, $pageWidth, $y, $discountLabel, '-' . $this->formatAmount($totals->discountAmount));
            $y -= 14;
        }

        // Nutzerwunsch: Arbeitszeit-Positionen als eigene Tabelle (analog
        // zur Artikeltabelle) statt als lange Summenzeilen, die sich mit
        // den Beträgen überschnitten haben.
        $laborRows = $this->buildLaborRows($detail);
        if ($laborRows !== []) {
            $y = $this->drawTableSection($writer, $y, $pageWidth, 'Arbeitszeit', ['Leistung', 'Menge', 'Einzelpreis', 'Summe'], $laborRows, $newPage, $pdfDesignSettings);
        }

        // Reservierter Platz für den restlichen Summenblock (Versandkosten,
        // Netto/USt/Brutto bzw. Kleinunternehmer-Hinweis).
        if ($y - 80 < self::MARGIN + 90) {
            $y = $newPage();
        }
        if ($laborRows !== []) {
            $y -= 6;
            $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
            $y -= 20;
        }

        if ($detail->invoice->shippingAmount !== null) {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Versandkosten:', '+' . $this->formatAmount((float) $detail->invoice->shippingAmount));
            $y -= 14;
        }

        if ($isSmallBusiness) {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Gesamtbetrag:', $this->formatAmount($totals->grossTotal), 10, true);
            $y -= 16;
            $writer->text(self::MARGIN, $y, 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.', 8.5);
            $y -= 24;
        } else {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Nettosumme:', $this->formatAmount($totals->netTotal));
            $y -= 14;
            $this->drawSummaryLine($writer, $pageWidth, $y, 'zzgl. ' . number_format((float) $vatRatePercent, 2, ',', '.') . '% USt:', $this->formatAmount($totals->taxAmount));
            $y -= 16;
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Gesamtbetrag (brutto):', $this->formatAmount($totals->grossTotal), 10, true);
            $y -= 30;
        }

        // Zahlungsart, Hinweistext der Zahlungsart und Bankverbindung
        // (Migrationen 105/106) unter der Summe.
        $paymentLines = $this->buildPaymentLines($detail, $invoiceSettings, $pageWidth - 2 * self::MARGIN);
        foreach ($paymentLines as $paymentLine) {
            if ($y < self::CONTENT_BOTTOM) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, $invoiceSettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
            }
            $writer->text(self::MARGIN, $y, $paymentLine, 8.5);
            $y -= 12;
        }
        if ($paymentLines !== []) {
            $y -= 10;
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
                self::CONTENT_BOTTOM,
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
                self::CONTENT_BOTTOM,
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

        // Fußzeile (Firma | Kontakt/Steuernummer | Bank) auf jeder Seite.
        $this->footer?->drawOnAllPages($writer, $detail->bankAccount);

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
                    'unitPrice' => number_format((float) $item->unitPrice, 2, ',', '.') . $this->currency(),
                    'lineTotal' => number_format($item->lineTotal(), 2, ',', '.') . $this->currency(),
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
                'unitPrice' => $hidePrice ? '–' : (number_format((float) $entry->item->unitPricePerDay, 2, ',', '.') . $this->currency()),
                'lineTotal' => $hidePrice ? '–' : (number_format($entry->lineTotal(), 2, ',', '.') . $this->currency()),
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

        // Zahlungsziel aus der Zahlungsart (Migration 106), Fallback auf das
        // alte firmenweite Zahlungsziel; die Zahlungsart selbst steht
        // ausführlich unter der Summe (siehe buildPaymentLines()).
        if ($invoice->dueDate !== null) {
            $lines[] = 'Fällig am: ' . $invoice->dueDate->format('d.m.Y');
        } else {
            $termDays = $detail->paymentMethod?->paymentTermDays ?? $invoiceSettings->paymentTermDays;
            $lines[] = $termDays <= 0
                ? 'Zahlungsziel: sofort fällig (voraussichtlich, noch nicht versendet).'
                : 'Zahlungsziel: ' . $termDays . ' Tage nach Rechnungsstellung (voraussichtlich, noch nicht versendet).';
        }

        // Die vormalige Zeile "Kunde: ..." entfällt hier — der Kunde steht
        // jetzt im Adressblock der Fensterkuvert-Zone (siehe
        // drawWindowEnvelopeAddress()).

        if ($invoice->eventLocation !== null && trim($invoice->eventLocation) !== '') {
            $lines[] = 'Ort: ' . $invoice->eventLocation;
        }

        // Zeitraum optional (siehe Migration 102) — ohne Zeitraum entfällt
        // die Zeile ganz, statt einen leeren/irreführenden Wert zu drucken.
        if ($invoice->startDate !== null && $invoice->endDate !== null) {
            $lines[] = 'Leistungszeitraum: ' . $invoice->startDate->format('d.m.Y') . ' - ' . $invoice->endDate->format('d.m.Y');
        }

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

    /**
     * Ansprechpartner-Auswahl (Migration 104): ist ein Ansprechpartner
     * gewählt, erscheint sein Name als zusätzliche Zeile; hat er eine EIGENE
     * Adresse (CustomerContact::hasOwnAddress()), werden die folgenden
     * Adresszeilen aus SEINER Adresse statt der Kundenadresse gebildet.
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

    /**
     * Zeilen unter der Summe: Zahlungsart mit Zahlungsziel bzw.
     * Fälligkeitsdatum, optionaler Hinweistext der Zahlungsart (umbrochen)
     * und die Bankverbindung (gewählt bzw. Standardkonto, sonst Fallback auf
     * die alten Bankfelder der InvoiceSettings).
     *
     * @return string[]
     */
    private function buildPaymentLines(InvoiceDetail $detail, InvoiceSettings $invoiceSettings, float $maxWidth): array
    {
        $lines = [];
        $paymentMethod = $detail->paymentMethod;
        $dueDate = $detail->invoice->dueDate;

        if ($paymentMethod !== null) {
            $term = $dueDate !== null
                ? ($paymentMethod->paymentTermDays <= 0 ? 'sofort fällig' : 'zahlbar bis ' . $dueDate->format('d.m.Y'))
                : $paymentMethod->termLabel() . ($paymentMethod->paymentTermDays > 0 ? ' nach Rechnungsstellung' : '');
            $lines[] = 'Zahlungsart: ' . $paymentMethod->name . ' – ' . $term;

            $note = $paymentMethod->renderNote($dueDate);
            if ($note !== null) {
                foreach ($this->wrapText($note, $maxWidth, 8.5, true) as $noteLine) {
                    $lines[] = $noteLine;
                }
            }
        }

        // Mit Fußzeile steht die Bankverbindung dort — nicht doppelt im Text.
        $bankLine = $this->footer === null ? $this->buildBankLine($detail->bankAccount, $invoiceSettings) : null;
        if ($bankLine !== null) {
            foreach ($this->wrapText($bankLine, $maxWidth, 8.5) as $bankLinePart) {
                $lines[] = $bankLinePart;
            }
        }

        return $lines;
    }

    private function buildBankLine(?BankAccount $bankAccount, InvoiceSettings $invoiceSettings): ?string
    {
        if ($bankAccount !== null) {
            $parts = [];
            if ($bankAccount->accountHolder !== null) {
                $parts[] = 'Kontoinhaber ' . $bankAccount->accountHolder;
            }
            if ($bankAccount->bankName !== null) {
                $parts[] = $bankAccount->bankName;
            }
            $parts[] = 'IBAN ' . $bankAccount->formattedIban();
            if ($bankAccount->bic !== null) {
                $parts[] = 'BIC ' . $bankAccount->bic;
            }

            return 'Bankverbindung: ' . implode(', ', $parts);
        }

        // Fallback: alte, einzelne Bankverbindung aus den Rechnungseinstellungen
        // (nur noch relevant, solange keine Bankverbindung angelegt ist).
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
        if ($y - 36 - $firstRowHeight < self::MARGIN + 90) {
            $y = $newPage();
        }

        $writer->text(self::MARGIN, $y, $title, 10, true);
        $y -= 8;
        $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings, $labels);

        foreach ($rows as $row) {
            $rowHeight = ArticlePdfRow::contentHeight($row, self::TABLE_LINE_HEIGHT) + self::TABLE_ROW_PADDING;
            if ($y - $rowHeight < self::MARGIN + 90) {
                $y = $newPage();
                $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings, $labels);
            }
            $this->drawRow($writer, $y, $row);
            $y -= $rowHeight;
        }

        return $y;
    }

    /** @return array<int, array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}> */
    private function buildLaborRows(InvoiceDetail $detail): array
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

    private function drawTerms(SimplePdfWriter $writer, InvoiceDetail $detail, CompanySettings $companySettings, InvoiceSettings $invoiceSettings, string $termsText, float $y, float $pageWidth, float $pageHeight, int $pageNumber, ?PdfDesignSettings $pdfDesignSettings = null): float
    {
        $writer->text(self::MARGIN, $y, 'Vermietbedingungen', 11, true);
        $y -= 18;

        $lines = $this->wrapText($termsText, $pageWidth - 2 * self::MARGIN, 8.0, true);

        foreach ($lines as $line) {
            if ($y < self::CONTENT_BOTTOM) {
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

    /** Währungsangabe hinter Beträgen (Einstellungen › PDF-Design, Migration 110). */
    private function currency(): string
    {
        return $this->amountFormatter?->suffix() ?? ' EUR';
    }
}
