<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Common\VenueResolver;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\LaborTime\LaborTimeBillingUnit;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use DateTimeImmutable;

/**
 * Gemeinsame Validierung für Create-/UpdateInvoiceHandler, strukturell
 * identisch zu ValidatesOfferInput (Angebote) — der Kunde ist auch hier
 * bewusst OPTIONAL beim Anlegen/Bearbeiten (erst MarkInvoiceSentHandler
 * verlangt zwingend einen Kunden + mindestens eine Position, siehe dort).
 * Zusätzlich gegenüber Angeboten: buyerReference (BT-10, freies Feld, z. B.
 * Bestellnummer des Kunden — siehe XRechnungGenerator), keine
 * validUntil-Entsprechung.
 */
trait ValidatesInvoiceInput
{
    /**
     * @param array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string, sort_order?: string, is_rental_item?: string}> $rawItems
     * @param array<int, array{labor_article_id: string, labor_user_id?: string, hours: string, labor_billing_unit?: string}> $rawLaborItems
     * @param array<int, array{description: string, quantity: string, unit_price: string, sort_order?: string}> $rawFreeTextItems
     * @param array<int, array{heading: string, sort_order?: string}> $rawSectionTexts
     * @return array{0: array<string, string>, 1: array{
     *     customerId: ?string, startDate: ?DateTimeImmutable, endDate: ?DateTimeImmutable,
     *     eventLocation: ?string, venueId: ?string, buyerReference: ?string, note: ?string,
     *     items: array<int, array{articleId: string, quantity: int, unitPrice: string, lineNote: ?string, sortOrder: int, isRentalItem: bool}>,
     *     laborItems: array<int, array{laborTimeArticleId: string, userId: ?string, hours: string, unitRatePerHour: string}>,
     *     freeTextItems: array<int, array{description: string, quantity: string, unitPrice: string, sortOrder: int}>,
     *     sectionTexts: array<int, array{heading: string, sortOrder: int}>,
     *     discountType: ?string, discountValue: ?string,
     *     rentalId: ?string,
     *     flatRateAmount: ?string,
     *     logisticsMethod: ?string, logisticsDate1: ?DateTimeImmutable, logisticsDate2: ?DateTimeImmutable,
     * }}
     */
    private function validateInvoiceInput(
        CustomerRepositoryInterface $customers,
        VenueRepositoryInterface $venues,
        ArticleRepositoryInterface $articles,
        LaborTimeArticleRepositoryInterface $laborTimeArticles,
        UserRepositoryInterface $users,
        string $customerId,
        string $rawStartDate,
        string $rawEndDate,
        string $eventLocation,
        string $buyerReference,
        string $note,
        array $rawItems,
        array $rawLaborItems,
        array $rawFreeTextItems = [],
        array $rawSectionTexts = [],
        ?string $rawDiscountType = null,
        ?string $rawDiscountValue = null,
        ?RentalRepositoryInterface $rentals = null,
        ?string $rawRentalId = null,
        bool $flatRateEnabled = false,
        ?string $rawFlatRateAmount = null,
        ?string $rawLogisticsMethod = null,
        ?string $rawLogisticsDate1 = null,
        ?string $rawLogisticsDate2 = null,
    ): array {
        $errors = [];

        $rentalId = $rawRentalId !== null ? trim($rawRentalId) : '';
        if ($rentalId !== '' && $rentals !== null && $rentals->findById($rentalId) === null) {
            $errors['rentalId'] = 'Die gewählte Veranstaltung wurde nicht gefunden.';
            $rentalId = '';
        }

        $flatRateAmount = null;
        if ($flatRateEnabled) {
            $rawAmount = str_replace(',', '.', trim((string) $rawFlatRateAmount));
            $amount = filter_var($rawAmount, FILTER_VALIDATE_FLOAT);
            if ($rawAmount === '' || $amount === false || $amount < 0) {
                $errors['flatRateAmount'] = 'Bitte einen gültigen Pauschalbetrag (0 oder größer) angeben.';
            } else {
                $flatRateAmount = number_format($amount, 2, '.', '');
            }
        }

        $logisticsMethod = $rawLogisticsMethod !== null ? trim($rawLogisticsMethod) : null;
        if ($logisticsMethod !== null && !in_array($logisticsMethod, ['pickup', 'delivery'], true)) {
            $logisticsMethod = null;
        }
        $logisticsDate1 = $this->parseInvoiceDateTime($rawLogisticsDate1);
        $logisticsDate2 = $this->parseInvoiceDateTime($rawLogisticsDate2);
        if ($logisticsDate1 === null && $logisticsDate2 === null) {
            // Ohne Termine ist die Methode fachlich bedeutungslos — nicht
            // mit abspeichern (bleibt auf der Rechnung unauffällig NULL).
            $logisticsMethod = null;
        }

        $customerId = trim($customerId);
        if ($customerId !== '' && $customers->findById($customerId) === null) {
            $errors['customerId'] = 'Der gewählte Kunde wurde nicht gefunden.';
        }

        [$resolvedLocation, $venueId] = VenueResolver::resolve($venues, $eventLocation);

        // Der Zeitraum ist optional (siehe Migration 102) — siehe
        // ValidatesOfferInput::validateOfferInput() für die identische
        // Begründung (eine Rechnung kann auch unabhängig von einer
        // konkreten Veranstaltung erstellt werden).
        $rawStartDateTrimmed = trim($rawStartDate);
        $rawEndDateTrimmed = trim($rawEndDate);

        $startDate = null;
        if ($rawStartDateTrimmed !== '') {
            $startDate = $this->parseInvoiceDate($rawStartDateTrimmed);
            if ($startDate === null) {
                $errors['startDate'] = 'Bitte ein gültiges Startdatum angeben.';
            }
        }

        $endDate = null;
        if ($rawEndDateTrimmed !== '') {
            $endDate = $this->parseInvoiceDate($rawEndDateTrimmed);
            if ($endDate === null) {
                $errors['endDate'] = 'Bitte ein gültiges Enddatum angeben.';
            }
        }

        if (($rawStartDateTrimmed === '') !== ($rawEndDateTrimmed === '')) {
            $errors['endDate'] = 'Bitte entweder Start- und Enddatum beide angeben oder beide leer lassen.';
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            $errors['endDate'] = 'Das Enddatum kann nicht vor dem Startdatum liegen.';
        }

        $note = trim($note);
        $buyerReference = trim($buyerReference);

        $items = [];
        foreach ($rawItems as $index => $rawItem) {
            $articleId = trim((string) ($rawItem['article_id'] ?? ''));
            if ($articleId === '') {
                continue;
            }

            if ($articles->findById($articleId) === null) {
                $errors['items'] = 'Mindestens eine Position enthält einen unbekannten Artikel.';

                continue;
            }

            $quantity = filter_var(trim((string) ($rawItem['quantity'] ?? '')), FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 1) {
                $errors['items'] = 'Bitte für jede Position eine Menge von mindestens 1 angeben.';

                continue;
            }

            $rawUnitPrice = trim((string) ($rawItem['unit_price'] ?? ''));
            $unitPrice = filter_var(str_replace(',', '.', $rawUnitPrice), FILTER_VALIDATE_FLOAT);
            if ($rawUnitPrice === '' || $unitPrice === false || $unitPrice < 0) {
                $errors['items'] = 'Bitte für jede Position einen gültigen Preis (0 oder größer) angeben.';

                continue;
            }

            $lineNote = trim((string) ($rawItem['line_note'] ?? ''));

            $items[] = [
                'articleId' => $articleId,
                'quantity' => $quantity,
                'unitPrice' => number_format($unitPrice, 2, '.', ''),
                'lineNote' => $lineNote !== '' ? $lineNote : null,
                'sortOrder' => (int) ($rawItem['sort_order'] ?? $index),
                // Default true (Mietposition) — siehe InvoiceItem::$isRentalItem.
                'isRentalItem' => ($rawItem['is_rental_item'] ?? '1') === '1',
            ];
        }

        $laborItems = [];
        foreach ($rawLaborItems as $rawLaborItem) {
            $laborArticleId = trim((string) ($rawLaborItem['labor_article_id'] ?? ''));
            if ($laborArticleId === '') {
                continue;
            }

            $laborArticle = $laborTimeArticles->findById($laborArticleId);
            if ($laborArticle === null) {
                $errors['laborItems'] = 'Mindestens eine Arbeitszeit-Position enthält einen unbekannten Arbeitszeit-Artikel.';

                continue;
            }

            // Abrechnungseinheit je Position wählbar (Stunde/Tag), aber nur
            // wenn für den gewählten Arbeitszeit-Artikel überhaupt ein
            // Tagessatz hinterlegt ist — sonst fällt die Auswahl auf Stunde
            // zurück (siehe LaborTimeBillingUnit).
            $billingUnit = LaborTimeBillingUnit::tryFrom(trim((string) ($rawLaborItem['labor_billing_unit'] ?? ''))) ?? LaborTimeBillingUnit::Hour;
            if ($billingUnit === LaborTimeBillingUnit::Day && $laborArticle->dailyRate === null) {
                $errors['laborItems'] = 'Für mindestens einen Arbeitszeit-Artikel ist keine Tagesabrechnung möglich, da kein Tagessatz hinterlegt ist.';

                continue;
            }

            $rawHours = trim((string) ($rawLaborItem['hours'] ?? ''));
            $hours = filter_var(str_replace(',', '.', $rawHours), FILTER_VALIDATE_FLOAT);
            if ($rawHours === '' || $hours === false || $hours <= 0) {
                $errors['laborItems'] = $billingUnit === LaborTimeBillingUnit::Day
                    ? 'Bitte für jede Arbeitszeit-Position eine gültige Anzahl Tage größer als 0 angeben.'
                    : 'Bitte für jede Arbeitszeit-Position eine gültige Stundenzahl größer als 0 angeben.';

                continue;
            }

            $laborUserId = trim((string) ($rawLaborItem['labor_user_id'] ?? ''));
            if ($laborUserId !== '' && $users->findById($laborUserId) === null) {
                $errors['laborItems'] = 'Mindestens eine Arbeitszeit-Position verweist auf einen unbekannten Mitarbeiter.';

                continue;
            }

            $laborItems[] = [
                'laborTimeArticleId' => $laborArticleId,
                'userId' => $laborUserId !== '' ? $laborUserId : null,
                'hours' => number_format($hours, 2, '.', ''),
                // Stunden- bzw. Tagessatz wird beim Speichern gesnapshotet
                // (siehe LaborTimeArticle-Klassendoc), NICHT frei eingebbar —
                // anders als unitPrice bei den Mietpositionen.
                'unitRatePerHour' => $billingUnit === LaborTimeBillingUnit::Day ? $laborArticle->dailyRate : $laborArticle->hourlyRate,
                'billingUnit' => $billingUnit,
            ];
        }

        // Kombirabatt: entweder 'percent' oder 'fixed', mit positivem
        // numerischem Wert (bei 'percent' zusätzlich <= 100). Siehe
        // App\Domain\Pricing\DocumentTotals für die Anwendung.
        $discountType = $rawDiscountType !== null && trim($rawDiscountType) !== '' ? trim($rawDiscountType) : null;
        $discountValue = null;
        if ($discountType !== null) {
            if (!in_array($discountType, ['percent', 'fixed'], true)) {
                $errors['discountType'] = 'Bitte einen gültigen Rabatt-Typ wählen.';
            } else {
                $rawValue = str_replace(',', '.', trim((string) $rawDiscountValue));
                $value = filter_var($rawValue, FILTER_VALIDATE_FLOAT);
                if ($rawValue === '' || $value === false || $value <= 0) {
                    $errors['discountValue'] = 'Bitte einen gültigen, positiven Rabattwert angeben.';
                } elseif ($discountType === 'percent' && $value > 100) {
                    $errors['discountValue'] = 'Ein prozentualer Rabatt kann nicht größer als 100 % sein.';
                } else {
                    $discountValue = number_format($value, 2, '.', '');
                }
            }
        }

        // Freitext-Posten/Zwischentexte: siehe
        // ValidatesOrderConfirmationInput::validateOrderConfirmationInput()
        // für die identische Begründung (orderedRows()-Konzept, zuerst für
        // Auftragsbestätigungen eingeführt und hier auf Rechnungen übertragen).
        $freeTextItems = [];
        foreach ($rawFreeTextItems as $index => $rawFreeTextItem) {
            $description = trim((string) ($rawFreeTextItem['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $rawQuantity = trim((string) ($rawFreeTextItem['quantity'] ?? '1'));
            $freeTextQuantity = filter_var(str_replace(',', '.', $rawQuantity), FILTER_VALIDATE_FLOAT);
            if ($rawQuantity === '' || $freeTextQuantity === false) {
                $errors['freeTextItems'] = 'Bitte für jeden Freitext-Posten eine gültige Menge angeben.';

                continue;
            }

            $rawUnitPrice = trim((string) ($rawFreeTextItem['unit_price'] ?? '0'));
            $freeTextUnitPrice = filter_var(str_replace(',', '.', $rawUnitPrice), FILTER_VALIDATE_FLOAT);
            if ($freeTextUnitPrice === false) {
                $errors['freeTextItems'] = 'Bitte für jeden Freitext-Posten einen gültigen Preis angeben.';

                continue;
            }

            $freeTextItems[] = [
                'description' => $description,
                'quantity' => number_format($freeTextQuantity, 2, '.', ''),
                'unitPrice' => number_format($freeTextUnitPrice, 2, '.', ''),
                'sortOrder' => (int) ($rawFreeTextItem['sort_order'] ?? (1000 + $index)),
            ];
        }

        $sectionTexts = [];
        foreach ($rawSectionTexts as $index => $rawSectionText) {
            $heading = trim((string) ($rawSectionText['heading'] ?? ''));
            if ($heading === '') {
                continue;
            }

            $sectionTexts[] = [
                'heading' => $heading,
                'sortOrder' => (int) ($rawSectionText['sort_order'] ?? (2000 + $index)),
            ];
        }

        // Geschäftsregel: Enthält die Rechnung AUSSCHLIESSLICH
        // Arbeitszeit-Positionen (keine Mietpositionen, keine Freitext-
        // Posten), dürfen Kombirabatt und Zubehörpauschale nicht verrechnet
        // werden (siehe Aufgabenstellung — beide würden sich fachlich auf die
        // Mietpreise/Freitext-Summe beziehen, die es in diesem Fall gar nicht
        // gibt). Der Kombirabatt wird dafür hier als Teil der Validierung
        // zurückgesetzt; die Zubehörpauschale ist kein Teil dieses Traits
        // (sie wird separat je Handler übergeben) und daher dort anhand
        // derselben Bedingung erzwungen — siehe CreateInvoiceHandler/
        // UpdateInvoiceHandler.
        if ($items === [] && $freeTextItems === [] && $laborItems !== []) {
            $discountType = null;
            $discountValue = null;
        }

        return [$errors, [
            'customerId' => $customerId !== '' ? $customerId : null,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'eventLocation' => $resolvedLocation,
            'venueId' => $venueId,
            'buyerReference' => $buyerReference !== '' ? $buyerReference : null,
            'note' => $note !== '' ? $note : null,
            'items' => $items,
            'laborItems' => $laborItems,
            'freeTextItems' => $freeTextItems,
            'sectionTexts' => $sectionTexts,
            'discountType' => $discountType,
            'discountValue' => $discountValue,
            'rentalId' => $rentalId !== '' ? $rentalId : null,
            'flatRateAmount' => $flatRateAmount,
            'logisticsMethod' => $logisticsMethod,
            'logisticsDate1' => $logisticsDate1,
            'logisticsDate2' => $logisticsDate2,
        ]];
    }

    private function parseInvoiceDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /** Wie parseInvoiceDate(), aber für datetime-local-Eingaben (Logistik-Termine) und akzeptiert null. */
    private function parseInvoiceDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return $this->parseInvoiceDate($value);
    }
}
