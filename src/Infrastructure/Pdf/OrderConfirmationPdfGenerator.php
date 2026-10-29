<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\OrderConfirmation\Query\OrderConfirmationDetail;
use App\Application\OrderConfirmation\Query\OrderConfirmationRow;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerContact;
use App\Domain\OrderConfirmation\OrderConfirmationFreeTextItem;
use App\Domain\OrderConfirmation\OrderConfirmationSectionText;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\PdfDesignSettings;
use App\Domain\Settings\PdfLayout\PdfLayoutSettings;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Vereinfachter Klon von OfferPdfGenerator für Auftragsbestätigungen:
 * gleicher Aufbau (Logo, Fensterkuvert-Adresszone, Kopfdaten, Tabelle,
 * Summenblock), aber bewusst OHNE globale Einleitungs-/Schlusstext-
 * Rich-Text-Einstellungen (keine eigenen PDF-Settings für
 * Auftragsbestätigungen, siehe OrderConfirmation-Klassen-Doc) und ohne
 * angehängte Vermietbedingungen — nur PDF-Download, kein E-Mail-Workflow.
 * Die Tabelle rendert orderedRows() (reguläre Positionen, Freitext-Posten
 * UND Zwischenüberschriften in einer gemeinsamen Reihenfolge), nicht nur
 * reine Artikelpositionen.
 *
 * Seit dem dokumenteigenen "Schlusstext" (OrderConfirmation::$closingText,
 * Kundenwunsch — siehe Invoice-/OfferPdfGenerator für die identische
 * Begründung) bekommt diese Klasse als EINZIGE Rich-Text-Abhängigkeit
 * $richTextRenderer injiziert, rein für dieses Feld — es gibt (anders als
 * bei Rechnung/Angebot) keinen globalen Einleitungs-/Schlusstext, der
 * dieselbe Infrastruktur bereits mitgebracht hätte.
 */
final class OrderConfirmationPdfGenerator
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
    ) {
    }

    public function generate(OrderConfirmationDetail $detail, CompanySettings $companySettings, ?PdfDesignSettings $pdfDesignSettings = null, ?PdfLayoutSettings $layout = null): string
    {
        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();

        $logoJpeg = $this->prepareLogoJpeg($companySettings);
        $logoBox = $logoJpeg !== null ? $this->fitBox($logoJpeg, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT) : null;

        $rows = $this->buildRows($detail);
        $pageNumber = 1;

        $writer->addPage();
        $y = $this->drawHeader($writer, $detail, $companySettings, $logoJpeg, $logoBox, $pageWidth, $pageHeight, $pageNumber, continuation: false, pdfDesignSettings: $pdfDesignSettings, layout: $layout);
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

        if ($y - 70 < self::MARGIN + 60) {
            $pageNumber++;
            $writer->addPage();
            $y = $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
        }

        $totals = $detail->totals();

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
        $y -= 20;

        // Nutzerwunsch: EINE "Zwischensumme" über alle Positionen der
        // Tabelle oben — Mietpositionen UND Freitext-Posten (gemeinsame Basis
        // für Zubehörpauschale/Kombirabatt, siehe DocumentTotals::calculate());
        // keine eigene "Freitext-Posten"-Zeile mehr.
        $this->drawSummaryLine($writer, $pageWidth, $y, 'Zwischensumme:', $this->formatAmount($totals->rentalNetTotal + $totals->freeTextTotal));
        $y -= 14;

        $orderConfirmation = $detail->orderConfirmation;
        if ($orderConfirmation->accessoryFlatRateEnabled && $orderConfirmation->accessoryFlatRatePercent !== null) {
            $this->drawSummaryLine($writer, $pageWidth, $y, 'Zubehörpauschale (' . number_format((float) $orderConfirmation->accessoryFlatRatePercent, 2, ',', '.') . '%):', '+' . $this->formatAmount($totals->accessoryFlatRateAmount));
            $y -= 14;
        }

        if ($orderConfirmation->discountType !== null && $orderConfirmation->discountValue !== null) {
            $discountLabel = $orderConfirmation->discountType === 'percent'
                ? 'Kombirabatt (' . number_format((float) $orderConfirmation->discountValue, 2, ',', '.') . '%):'
                : 'Kombirabatt (Festbetrag):';
            $this->drawSummaryLine($writer, $pageWidth, $y, $discountLabel, '-' . $this->formatAmount($totals->discountAmount));
            $y -= 14;
        }

        $y -= 4;
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

        // Seitenumbruch-Helfer für den Zahlungsbedingungen-Block.
        $newPage = function () use ($writer, $detail, $pageWidth, $pageHeight, $pdfDesignSettings, &$pageNumber): float {
            $pageNumber++;
            $writer->addPage();

            return $this->drawHeader($writer, $detail, null, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings) - 20;
        };

        // Zahlungsbedingungen (Zahlungsart mit Zahlungsziel, Migration 106)
        // unter der Summe — das Zahlungsziel gilt ab Rechnungsstellung.
        if ($detail->paymentMethod !== null) {
            $paymentMethod = $detail->paymentMethod;
            $paymentLines = ['Zahlungsbedingungen: ' . $paymentMethod->name . ' – ' . $paymentMethod->termLabel() . ($paymentMethod->paymentTermDays > 0 ? ' nach Rechnungsstellung' : '')];
            $note = $paymentMethod->renderNote();
            if ($note !== null) {
                foreach ($this->wrapText(str_replace(["\r\n", "\n"], ' ', $note), $pageWidth - 2 * self::MARGIN, 8.5) as $noteLine) {
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

        // Dokumenteigener Schlusstext (OrderConfirmation::$closingText) —
        // siehe Klassen-Doc oben: einziger Rich-Text-Anwendungsfall dieser
        // Klasse, identisches Rendering-Muster wie in Invoice-/
        // OfferPdfGenerator::generate().
        $documentClosingBlocks = $this->richTextRenderer->parse($orderConfirmation->closingText);
        if ($documentClosingBlocks !== []) {
            $documentClosingPageNumber = $pageNumber;
            $y = $this->richTextRenderer->render(
                $writer,
                $documentClosingBlocks,
                self::MARGIN,
                $y - 10.0,
                $pageWidth - 2 * self::MARGIN,
                self::CONTENT_BOTTOM,
                function () use ($writer, $detail, $companySettings, $pageWidth, $pageHeight, $pdfDesignSettings, &$documentClosingPageNumber): float {
                    $documentClosingPageNumber++;
                    $writer->addPage();

                    return $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $documentClosingPageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                }
            );
        }

        // Fußzeile (Firma | Kontakt/Steuernummer | Bank) auf jeder Seite.
        $this->footer?->drawOnAllPages($writer, null);

        return $writer->output();
    }

    /** @return array<int, array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}> */
    private function buildRows(OrderConfirmationDetail $detail): array
    {
        $columns = $this->columnLayout();
        $rows = [];

        foreach ($detail->orderedRows() as $row) {
            if ($row->type === OrderConfirmationRow::TYPE_SECTION) {
                /** @var OrderConfirmationSectionText $sectionText */
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

            if ($row->type === OrderConfirmationRow::TYPE_FREE_TEXT) {
                /** @var OrderConfirmationFreeTextItem $item */
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
                'unitPrice' => number_format((float) $entry->item->unitPricePerDay, 2, ',', '.') . ' EUR',
                'lineTotal' => number_format($entry->lineTotal(), 2, ',', '.') . ' EUR',
                'isSection' => false,
            ];
        }

        return $rows;
    }

    private function drawHeader(
        SimplePdfWriter $writer,
        OrderConfirmationDetail $detail,
        ?CompanySettings $companySettings,
        ?string $logoJpeg,
        ?array $logoBox,
        float $pageWidth,
        float $pageHeight,
        int $pageNumber,
        bool $continuation = false,
        ?PdfDesignSettings $pdfDesignSettings = null,
        ?PdfLayoutSettings $layout = null
    ): float {
        $this->drawFoldMarks($writer, $pageHeight);

        if ($logoJpeg !== null && $logoBox !== null) {
            $logoOverride = $layout?->get('logo');
            if ($logoOverride !== null) {
                $logoPoint = $logoOverride->toPdfPoint($pageHeight);
                $writer->image($logoJpeg, $logoPoint['x'], $logoPoint['y'] - $logoBox[1], $logoBox[0], $logoBox[1]);
            } else {
                $logoX = $pdfDesignSettings?->logoPosition === PdfDesignSettings::LOGO_POSITION_LEFT
                    ? self::MARGIN
                    : $pageWidth - self::MARGIN - $logoBox[0];
                $writer->image($logoJpeg, $logoX, $pageHeight - self::MARGIN - $logoBox[1], $logoBox[0], $logoBox[1]);
            }
        }

        if (!$continuation && $companySettings !== null) {
            $this->drawWindowEnvelopeAddress($writer, $detail, $companySettings, $pageHeight, $layout);
        }

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
        $title = $continuation ? 'Auftragsbestätigung (Fortsetzung)' : 'Auftragsbestätigung';
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

            foreach ($this->buildInfoLines($detail) as $line) {
                $writer->text($infoLinesX, $y, $line, $infoLinesFontSize);
                $y -= 14;
            }
        }

        $writer->text($pageWidth - self::MARGIN - 50, $pageHeight - self::MARGIN - 60, 'Seite ' . $pageNumber, 8);

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
        $y -= 20;

        return $y;
    }

    /** @return string[] */
    private function buildInfoLines(OrderConfirmationDetail $detail): array
    {
        $orderConfirmation = $detail->orderConfirmation;
        $lines = [];
        $lines[] = 'Auftragsbestätigungsnummer: ' . $orderConfirmation->orderConfirmationNumber;
        $lines[] = 'Datum: ' . $orderConfirmation->createdAt->format('d.m.Y');

        if ($orderConfirmation->eventLocation !== null && trim($orderConfirmation->eventLocation) !== '') {
            $lines[] = 'Ort: ' . $orderConfirmation->eventLocation;
        }

        // Siehe InvoicePdfGenerator für die identische Begründung (Zeitraum
        // optional seit Migration 102).
        if ($orderConfirmation->startDate !== null && $orderConfirmation->endDate !== null) {
            $lines[] = 'Zeitraum: ' . $orderConfirmation->startDate->format('d.m.Y') . ' - ' . $orderConfirmation->endDate->format('d.m.Y');
        }

        if ($orderConfirmation->note !== null && trim($orderConfirmation->note) !== '') {
            $lines[] = 'Anmerkung: ' . $orderConfirmation->note;
        }

        return $lines;
    }

    private function drawFoldMarks(SimplePdfWriter $writer, float $pageHeight): void
    {
        foreach (self::FOLD_MARK_AT_MM as $mmFromTop) {
            $y = $pageHeight - ($mmFromTop * self::MM_TO_PT);
            $writer->line(0.0, $y, self::FOLD_MARK_LENGTH_MM * self::MM_TO_PT, $y, 0.5, '0.5 0.5 0.5');
        }
    }

    private function drawWindowEnvelopeAddress(SimplePdfWriter $writer, OrderConfirmationDetail $detail, CompanySettings $companySettings, float $pageHeight, ?PdfLayoutSettings $layout = null): void
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
    private function drawTableHeader(SimplePdfWriter $writer, float $y, float $pageWidth, ?PdfDesignSettings $pdfDesignSettings = null, array $labels = ['Bezeichnung', 'Menge', 'Einzelpreis', 'Summe']): float
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
        return number_format($amount, 2, ',', '.') . ' EUR';
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

    /** @return string[] */
    private function wrapText(string $text, float $maxWidth, float $fontSize): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }

        $avgCharWidth = $fontSize * 0.52;
        $maxChars = max(6, (int) floor($maxWidth / $avgCharWidth));

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
