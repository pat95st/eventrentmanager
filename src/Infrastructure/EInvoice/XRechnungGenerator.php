<?php

declare(strict_types=1);

namespace App\Infrastructure\EInvoice;

use App\Application\Invoice\Query\InvoiceDetail;
use App\Domain\Exception\ConflictException;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\InvoiceSettings;
use DOMDocument;
use DOMElement;

/**
 * Erzeugt aus einer finalisierten Rechnung ein UBL-2.1-Invoice-XML, das sich
 * so nah wie mit dieser Feldzuordnung von Hand vertretbar an die XRechnung
 * 3.x-CIUS (die deutsche Kernspezifikation von EN 16931) hält.
 *
 * WICHTIG — bitte vor produktivem Einsatz lesen: dieser Generator wurde
 * NICHT gegen einen offiziellen XSD-/Schematron-Validator geprüft (in dieser
 * Sandbox-Umgebung nicht verfügbar). Er bildet nach bestem Wissen die unten
 * mit BT-/BG-Nummern (Business Term/Business Group aus EN 16931) zitierte
 * Feldzuordnung ab, erhebt aber keinen Anspruch auf vollständige, geprüfte
 * Rechtskonformität. Vor produktivem Einsatz sollte das erzeugte XML gegen
 * den kostenlosen KoSIT-XRechnung-Validator oder das E-Rechnungsportal des
 * jeweiligen Empfängers geprüft werden — insbesondere für Kunden/Szenarien,
 * die diese Zuordnung nicht ausdrücklich abdeckt (z. B. Kunden außerhalb
 * Deutschlands, siehe Länder-Anmerkung unten).
 *
 * ANNAHME (siehe App\Domain\Customer\Customer — kein $country-Feld): dieses
 * Geschäft ist ausschließlich in Deutschland tätig; sowohl die Länder der
 * Verkäufer- (BT-40) als auch der Käuferadresse (BT-55) werden daher fest
 * mit "DE" belegt. Sollten künftig Kunden außerhalb Deutschlands
 * hinzukommen, muss Customer zwingend um ein echtes Länderfeld erweitert
 * werden — diese Annahme dann NICHT mehr gültig.
 *
 * Nur für eine FINALISIERTE Rechnung aufrufbar (invoiceNumber/invoiceDate
 * gesetzt, siehe Invoice::isFinalized()) — für einen Entwurf wird eine
 * ConflictException geworfen, damit nie ein unvollständiges/leeres
 * E-Rechnungs-XML entsteht.
 */
final class XRechnungGenerator
{
    /**
     * @param string|null $sellerEmail Kontakt-E-Mail des Verkäufers (BG-6 /
     *        cac:Contact), z. B. aus SmtpSettings::$fromAddress — optional,
     *        siehe Klassen-Doc "Contact" im UBL-Ausschnitt der
     *        Aufgabenstellung.
     */
    public function generate(
        InvoiceDetail $detail,
        CompanySettings $companySettings,
        InvoiceSettings $invoiceSettings,
        ?string $sellerEmail = null
    ): string {
        $invoice = $detail->invoice;

        if (!$invoice->isFinalized() || $invoice->invoiceNumber === null || $invoice->invoiceDate === null) {
            throw new ConflictException('Für eine noch nicht versendete Rechnung (ohne Rechnungsnummer) kann kein XRechnung-XML erzeugt werden.');
        }

        $isSmallBusiness = $invoice->isSmallBusiness ?? $invoiceSettings->isSmallBusiness;
        $vatRatePercent = $invoice->vatRatePercent ?? $invoiceSettings->vatRatePercent;

        // Zentrale Summenberechnung (Mietpreise -> Zubehörpauschale ->
        // Kombirabatt -> Netto/USt/Brutto), siehe App\Domain\Pricing\DocumentTotals
        // — die Zubehörpauschale/der Kombirabatt werden unten als
        // dokumentenweite cac:AllowanceCharge (BG-20/BG-21) abgebildet,
        // damit LineExtensionAmount (Summe der Zeilen) weiterhin zur Summe
        // der InvoiceLine-Beträge passt (UBL-Konsistenzregel BR-CO-10).
        $totals = $detail->totals($invoiceSettings);
        $netTotal = $totals->netTotal;
        $taxAmount = $totals->taxAmount;
        $grossTotal = $totals->grossTotal;

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:cac',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2'
        );
        $root->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:cbc',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2'
        );
        $dom->appendChild($root);

        $cbc = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        $cac = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

        $this->append($dom, $root, $cbc, 'cbc:CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_3.0');
        $this->append($dom, $root, $cbc, 'cbc:ProfileID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
        $this->append($dom, $root, $cbc, 'cbc:ID', $invoice->invoiceNumber); // BT-1
        $this->append($dom, $root, $cbc, 'cbc:IssueDate', $invoice->invoiceDate->format('Y-m-d')); // BT-2
        if ($invoice->dueDate !== null) {
            $this->append($dom, $root, $cbc, 'cbc:DueDate', $invoice->dueDate->format('Y-m-d')); // BT-9
        }
        $this->append($dom, $root, $cbc, 'cbc:InvoiceTypeCode', '380'); // BT-3
        $this->append($dom, $root, $cbc, 'cbc:DocumentCurrencyCode', 'EUR'); // BT-5

        // BT-10 ist in XRechnung verpflichtend; ist keine Referenz hinterlegt,
        // wird pragmatisch die Rechnungsnummer selbst als Fallback genutzt.
        $buyerReference = $invoice->buyerReference !== null && trim($invoice->buyerReference) !== ''
            ? $invoice->buyerReference
            : $invoice->invoiceNumber;
        $this->append($dom, $root, $cbc, 'cbc:BuyerReference', $buyerReference); // BT-10

        // --- BG-4 Seller -----------------------------------------------
        $supplierParty = $this->appendEl($dom, $root, $cac, 'cac:AccountingSupplierParty');
        $sellerParty = $this->appendEl($dom, $supplierParty, $cac, 'cac:Party');

        $sellerAddress = $this->appendEl($dom, $sellerParty, $cac, 'cac:PostalAddress'); // BG-5
        $this->append($dom, $sellerAddress, $cbc, 'cbc:StreetName', $companySettings->street ?? '');
        $this->append($dom, $sellerAddress, $cbc, 'cbc:CityName', $companySettings->city ?? '');
        $this->append($dom, $sellerAddress, $cbc, 'cbc:PostalZone', $companySettings->postalCode ?? '');
        $sellerCountry = $this->appendEl($dom, $sellerAddress, $cac, 'cac:Country');
        $this->append($dom, $sellerCountry, $cbc, 'cbc:IdentificationCode', 'DE'); // BT-40, siehe Klassen-Doc

        if ($invoiceSettings->vatId !== null && trim($invoiceSettings->vatId) !== '') {
            $sellerTaxScheme = $this->appendEl($dom, $sellerParty, $cac, 'cac:PartyTaxScheme');
            $this->append($dom, $sellerTaxScheme, $cbc, 'cbc:CompanyID', $invoiceSettings->vatId); // BT-31
            $sellerTaxSchemeId = $this->appendEl($dom, $sellerTaxScheme, $cac, 'cac:TaxScheme');
            $this->append($dom, $sellerTaxSchemeId, $cbc, 'cbc:ID', 'VAT');
        }

        $sellerLegalEntity = $this->appendEl($dom, $sellerParty, $cac, 'cac:PartyLegalEntity');
        $this->append($dom, $sellerLegalEntity, $cbc, 'cbc:RegistrationName', $companySettings->name ?? ''); // BT-27

        if ($sellerEmail !== null && trim($sellerEmail) !== '') {
            $sellerContact = $this->appendEl($dom, $sellerParty, $cac, 'cac:Contact');
            $this->append($dom, $sellerContact, $cbc, 'cbc:ElectronicMail', $sellerEmail);
        }

        // --- BG-7 Buyer --------------------------------------------------
        $customerParty = $this->appendEl($dom, $root, $cac, 'cac:AccountingCustomerParty');
        $buyerParty = $this->appendEl($dom, $customerParty, $cac, 'cac:Party');

        $buyerAddress = $this->appendEl($dom, $buyerParty, $cac, 'cac:PostalAddress'); // BG-8
        $customer = $detail->customer;
        $this->append($dom, $buyerAddress, $cbc, 'cbc:StreetName', $customer?->street ?? '');
        $this->append($dom, $buyerAddress, $cbc, 'cbc:CityName', $customer?->city ?? '');
        $this->append($dom, $buyerAddress, $cbc, 'cbc:PostalZone', $customer?->postalCode ?? '');
        $buyerCountry = $this->appendEl($dom, $buyerAddress, $cac, 'cac:Country');
        $this->append($dom, $buyerCountry, $cbc, 'cbc:IdentificationCode', 'DE'); // BT-55, siehe Klassen-Doc

        $buyerLegalEntity = $this->appendEl($dom, $buyerParty, $cac, 'cac:PartyLegalEntity');
        $this->append($dom, $buyerLegalEntity, $cbc, 'cbc:RegistrationName', $customer?->displayName() ?? ''); // BT-44

        // --- BG-16 Payment means ------------------------------------------
        // Gewählte bzw. Standard-Bankverbindung (Migration 105), Fallback auf
        // die alte Bankverbindung aus den Rechnungseinstellungen.
        $bankAccount = $detail->bankAccount;
        $payeeIban = $bankAccount?->iban ?? ($invoiceSettings->iban !== null ? str_replace(' ', '', trim($invoiceSettings->iban)) : null);
        $payeeAccountName = $bankAccount !== null ? ($bankAccount->accountHolder ?? $bankAccount->bankName) : $invoiceSettings->bankName;
        $payeeBic = $bankAccount !== null ? $bankAccount->bic : $invoiceSettings->bic;
        if ($payeeIban !== null && $payeeIban !== '') {
            $paymentMeans = $this->appendEl($dom, $root, $cac, 'cac:PaymentMeans');
            $this->append($dom, $paymentMeans, $cbc, 'cbc:PaymentMeansCode', '58');
            $account = $this->appendEl($dom, $paymentMeans, $cac, 'cac:PayeeFinancialAccount');
            $this->append($dom, $account, $cbc, 'cbc:ID', $payeeIban); // BT-84
            if ($payeeAccountName !== null && trim($payeeAccountName) !== '') {
                $this->append($dom, $account, $cbc, 'cbc:Name', $payeeAccountName); // BT-85
            }
            if ($payeeBic !== null && trim($payeeBic) !== '') {
                $branch = $this->appendEl($dom, $account, $cac, 'cac:FinancialInstitutionBranch');
                $this->append($dom, $branch, $cbc, 'cbc:ID', trim($payeeBic)); // BT-86
            }
        }

        // --- BT-20 Payment terms (Zahlungsart mit Zahlungsziel, Migration 106)
        if ($detail->paymentMethod !== null) {
            $paymentTermsText = $detail->paymentMethod->name . ' – '
                . ($invoice->dueDate !== null && $detail->paymentMethod->paymentTermDays > 0
                    ? 'zahlbar bis ' . $invoice->dueDate->format('d.m.Y')
                    : $detail->paymentMethod->termLabel());
            $note = $detail->paymentMethod->renderNote($invoice->dueDate);
            if ($note !== null) {
                $paymentTermsText .= '. ' . $note;
            }
            $paymentTerms = $this->appendEl($dom, $root, $cac, 'cac:PaymentTerms');
            $this->append($dom, $paymentTerms, $cbc, 'cbc:Note', $paymentTermsText);
        }

        $taxCategoryId = $isSmallBusiness ? 'E' : 'S'; // BT-118
        $taxPercent = $isSmallBusiness ? '0.00' : $this->money((float) $vatRatePercent);

        // --- BG-20/BG-21 Document-level allowances/charges ----------------
        // Zubehörpauschale (Zuschlag) und Kombirabatt (Nachlass) werden hier
        // als dokumentenweite AllowanceCharge abgebildet (siehe oben) —
        // beide brauchen laut EN 16931 zwingend eine eigene TaxCategory.
        if ($invoice->accessoryFlatRateEnabled && $invoice->accessoryFlatRatePercent !== null && $totals->accessoryFlatRateAmount > 0.0) {
            $charge = $this->appendEl($dom, $root, $cac, 'cac:AllowanceCharge');
            $this->append($dom, $charge, $cbc, 'cbc:ChargeIndicator', 'true'); // BG-21
            $this->append($dom, $charge, $cbc, 'cbc:AllowanceChargeReason', 'Zubehörpauschale'); // BT-104
            $this->append($dom, $charge, $cbc, 'cbc:Amount', $this->money($totals->accessoryFlatRateAmount), ['currencyID' => 'EUR']); // BT-99
            $chargeTaxCategory = $this->appendEl($dom, $charge, $cac, 'cac:TaxCategory');
            $this->append($dom, $chargeTaxCategory, $cbc, 'cbc:ID', $taxCategoryId);
            $this->append($dom, $chargeTaxCategory, $cbc, 'cbc:Percent', $taxPercent);
            $chargeTaxScheme = $this->appendEl($dom, $chargeTaxCategory, $cac, 'cac:TaxScheme');
            $this->append($dom, $chargeTaxScheme, $cbc, 'cbc:ID', 'VAT');
        }

        if ($invoice->discountType !== null && $invoice->discountValue !== null && $totals->discountAmount > 0.0) {
            $allowance = $this->appendEl($dom, $root, $cac, 'cac:AllowanceCharge');
            $this->append($dom, $allowance, $cbc, 'cbc:ChargeIndicator', 'false'); // BG-20
            $this->append($dom, $allowance, $cbc, 'cbc:AllowanceChargeReason', 'Kombirabatt'); // BT-97
            $this->append($dom, $allowance, $cbc, 'cbc:Amount', $this->money($totals->discountAmount), ['currencyID' => 'EUR']); // BT-92
            $allowanceTaxCategory = $this->appendEl($dom, $allowance, $cac, 'cac:TaxCategory');
            $this->append($dom, $allowanceTaxCategory, $cbc, 'cbc:ID', $taxCategoryId);
            $this->append($dom, $allowanceTaxCategory, $cbc, 'cbc:Percent', $taxPercent);
            $allowanceTaxScheme = $this->appendEl($dom, $allowanceTaxCategory, $cac, 'cac:TaxScheme');
            $this->append($dom, $allowanceTaxScheme, $cbc, 'cbc:ID', 'VAT');
        }

        // Versandkosten (nur beim Verkauf-Workflow, siehe
        // SellInventoryNumberHandler/Invoice::$shippingAmount): ebenfalls ein
        // dokumentenweiter Zuschlag, analog zur Zubehörpauschale — es gibt
        // dafür bewusst KEINE eigene InvoiceLine (kein verkaufbarer Artikel).
        if ($invoice->shippingAmount !== null && $totals->shippingAmount > 0.0) {
            $shippingCharge = $this->appendEl($dom, $root, $cac, 'cac:AllowanceCharge');
            $this->append($dom, $shippingCharge, $cbc, 'cbc:ChargeIndicator', 'true'); // BG-21
            $this->append($dom, $shippingCharge, $cbc, 'cbc:AllowanceChargeReason', 'Versandkosten'); // BT-104
            $this->append($dom, $shippingCharge, $cbc, 'cbc:Amount', $this->money($totals->shippingAmount), ['currencyID' => 'EUR']); // BT-99
            $shippingTaxCategory = $this->appendEl($dom, $shippingCharge, $cac, 'cac:TaxCategory');
            $this->append($dom, $shippingTaxCategory, $cbc, 'cbc:ID', $taxCategoryId);
            $this->append($dom, $shippingTaxCategory, $cbc, 'cbc:Percent', $taxPercent);
            $shippingTaxScheme = $this->appendEl($dom, $shippingTaxCategory, $cac, 'cac:TaxScheme');
            $this->append($dom, $shippingTaxScheme, $cbc, 'cbc:ID', 'VAT');
        }

        // --- BG-23 Tax total -----------------------------------------------
        $taxTotal = $this->appendEl($dom, $root, $cac, 'cac:TaxTotal');
        $this->append($dom, $taxTotal, $cbc, 'cbc:TaxAmount', $this->money($taxAmount), ['currencyID' => 'EUR']); // BT-110
        $taxSubtotal = $this->appendEl($dom, $taxTotal, $cac, 'cac:TaxSubtotal');
        $this->append($dom, $taxSubtotal, $cbc, 'cbc:TaxableAmount', $this->money($netTotal), ['currencyID' => 'EUR']); // BT-116
        $this->append($dom, $taxSubtotal, $cbc, 'cbc:TaxAmount', $this->money($taxAmount), ['currencyID' => 'EUR']); // BT-117
        $taxCategory = $this->appendEl($dom, $taxSubtotal, $cac, 'cac:TaxCategory');
        $this->append($dom, $taxCategory, $cbc, 'cbc:ID', $taxCategoryId);
        $this->append($dom, $taxCategory, $cbc, 'cbc:Percent', $taxPercent); // BT-119
        if ($isSmallBusiness) {
            $this->append($dom, $taxCategory, $cbc, 'cbc:TaxExemptionReason', 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.'); // BT-121
        }
        $taxCategoryScheme = $this->appendEl($dom, $taxCategory, $cac, 'cac:TaxScheme');
        $this->append($dom, $taxCategoryScheme, $cbc, 'cbc:ID', 'VAT');

        // --- BG-22 Legal monetary total --------------------------------
        // Reihenfolge der Kindelemente ist durch das UBL-Schema fest
        // vorgegeben: LineExtensionAmount, TaxExclusiveAmount,
        // TaxInclusiveAmount, AllowanceTotalAmount, ChargeTotalAmount,
        // PayableAmount.
        $monetaryTotal = $this->appendEl($dom, $root, $cac, 'cac:LegalMonetaryTotal');
        $this->append($dom, $monetaryTotal, $cbc, 'cbc:LineExtensionAmount', $this->money($totals->rentalNetTotal + $totals->laborTimeTotal), ['currencyID' => 'EUR']); // BT-106 (Summe ALLER Zeilen — Mietpositionen + Arbeitszeit —, VOR Zubehörpauschale/Kombirabatt)
        $this->append($dom, $monetaryTotal, $cbc, 'cbc:TaxExclusiveAmount', $this->money($netTotal), ['currencyID' => 'EUR']); // BT-109
        $this->append($dom, $monetaryTotal, $cbc, 'cbc:TaxInclusiveAmount', $this->money($grossTotal), ['currencyID' => 'EUR']); // BT-112
        if ($totals->discountAmount > 0.0) {
            $this->append($dom, $monetaryTotal, $cbc, 'cbc:AllowanceTotalAmount', $this->money($totals->discountAmount), ['currencyID' => 'EUR']); // BT-107
        }
        if ($totals->accessoryFlatRateAmount > 0.0 || $totals->shippingAmount > 0.0) {
            $this->append($dom, $monetaryTotal, $cbc, 'cbc:ChargeTotalAmount', $this->money($totals->accessoryFlatRateAmount + $totals->shippingAmount), ['currencyID' => 'EUR']); // BT-108
        }
        $this->append($dom, $monetaryTotal, $cbc, 'cbc:PayableAmount', $this->money($grossTotal), ['currencyID' => 'EUR']); // BT-115

        // --- BG-25 Invoice lines -----------------------------------------
        $lineIndex = 1;
        foreach ($detail->items as $entry) {
            $line = $this->appendEl($dom, $root, $cac, 'cac:InvoiceLine');
            $this->append($dom, $line, $cbc, 'cbc:ID', (string) $lineIndex); // BT-126
            $this->append($dom, $line, $cbc, 'cbc:InvoicedQuantity', (string) $entry->item->quantity, ['unitCode' => 'C62']); // BT-129
            $this->append($dom, $line, $cbc, 'cbc:LineExtensionAmount', $this->money($entry->lineTotal()), ['currencyID' => 'EUR']); // BT-131

            $item = $this->appendEl($dom, $line, $cac, 'cac:Item');
            $this->append($dom, $item, $cbc, 'cbc:Name', $entry->displayName()); // BT-153
            $classifiedTaxCategory = $this->appendEl($dom, $item, $cac, 'cac:ClassifiedTaxCategory');
            $this->append($dom, $classifiedTaxCategory, $cbc, 'cbc:ID', $taxCategoryId);
            $this->append($dom, $classifiedTaxCategory, $cbc, 'cbc:Percent', $taxPercent);
            $lineTaxScheme = $this->appendEl($dom, $classifiedTaxCategory, $cac, 'cac:TaxScheme');
            $this->append($dom, $lineTaxScheme, $cbc, 'cbc:ID', 'VAT');

            $price = $this->appendEl($dom, $line, $cac, 'cac:Price');
            $this->append($dom, $price, $cbc, 'cbc:PriceAmount', $this->money((float) $entry->item->unitPricePerDay), ['currencyID' => 'EUR']); // BT-146

            $lineIndex++;
        }

        foreach ($detail->laborItems as $entry) {
            $line = $this->appendEl($dom, $root, $cac, 'cac:InvoiceLine');
            $this->append($dom, $line, $cbc, 'cbc:ID', (string) $lineIndex); // BT-126
            // Einheitencode richtet sich nach der tatsächlichen Abrechnung der
            // Position (siehe App\Domain\LaborTime\LaborTimeBillingUnit):
            // HUR = Stunden, DAY = Tage (beide UN/CEFACT Rec. 20).
            $laborUnitCode = $entry->item->billingUnit === \App\Domain\LaborTime\LaborTimeBillingUnit::Day ? 'DAY' : 'HUR';
            $this->append($dom, $line, $cbc, 'cbc:InvoicedQuantity', $entry->item->hours, ['unitCode' => $laborUnitCode]); // BT-129
            $this->append($dom, $line, $cbc, 'cbc:LineExtensionAmount', $this->money($entry->lineTotal()), ['currencyID' => 'EUR']); // BT-131

            $item = $this->appendEl($dom, $line, $cac, 'cac:Item');
            $this->append($dom, $item, $cbc, 'cbc:Name', $entry->laborTimeArticle->name); // BT-153
            $classifiedTaxCategory = $this->appendEl($dom, $item, $cac, 'cac:ClassifiedTaxCategory');
            $this->append($dom, $classifiedTaxCategory, $cbc, 'cbc:ID', $taxCategoryId);
            $this->append($dom, $classifiedTaxCategory, $cbc, 'cbc:Percent', $taxPercent);
            $lineTaxScheme = $this->appendEl($dom, $classifiedTaxCategory, $cac, 'cac:TaxScheme');
            $this->append($dom, $lineTaxScheme, $cbc, 'cbc:ID', 'VAT');

            $price = $this->appendEl($dom, $line, $cac, 'cac:Price');
            $this->append($dom, $price, $cbc, 'cbc:PriceAmount', $this->money((float) $entry->item->unitRatePerHour), ['currencyID' => 'EUR']); // BT-146

            $lineIndex++;
        }

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new ConflictException('Das XRechnung-XML konnte nicht erzeugt werden.');
        }

        return $xml;
    }

    /**
     * Formatiert einen Geldbetrag IMMER mit "." als Dezimaltrennzeichen
     * (UBL/XML-Vorgabe) — bewusst NICHT number_format($v, 2, ',', '.') wie
     * bei der deutschen Bildschirm-/PDF-Darstellung an anderer Stelle im
     * Code, das wäre hier ungültig.
     */
    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /** @param array<string, string> $attributes */
    private function append(DOMDocument $dom, DOMElement $parent, string $namespace, string $qualifiedName, string $value, array $attributes = []): DOMElement
    {
        $el = $dom->createElementNS($namespace, $qualifiedName, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        foreach ($attributes as $attrName => $attrValue) {
            $el->setAttribute($attrName, $attrValue);
        }
        $parent->appendChild($el);

        return $el;
    }

    private function appendEl(DOMDocument $dom, DOMElement $parent, string $namespace, string $qualifiedName): DOMElement
    {
        $el = $dom->createElementNS($namespace, $qualifiedName);
        $parent->appendChild($el);

        return $el;
    }
}
