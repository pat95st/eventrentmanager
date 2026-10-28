<?php

declare(strict_types=1);

namespace App\Application\Offer\Command;

use App\Application\Common\VenueResolver;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerContactRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\LaborTime\LaborTimeBillingUnit;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Sale\SaleRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Shared\Money;
use DateTimeImmutable;

/**
 * Gemeinsame Validierung für Create-/UpdateOfferHandler, analog zu
 * ValidatesRentalInput (Vermietungen). Der Kunde ist beim Angebot bewusst
 * OPTIONAL (ein Angebot kann z. B. vor der endgültigen Kundenzuordnung als
 * grober Entwurf begonnen werden), anders als bei der Vermietung.
 */
trait ValidatesOfferInput
{
    /**
     * @param array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string, sort_order?: string}> $rawItems
     * @param array<int, array{labor_article_id: string, labor_user_id?: string, hours: string, labor_billing_unit?: string}> $rawLaborItems
     * @param array<int, array{article_id: string, source: string, inventory_number_id: string, quantity: string, unit_price: string, line_note: string}> $rawSaleItems
     * @param array<int, array{description: string, quantity: string, unit_price: string, sort_order?: string}> $rawFreeTextItems
     * @param array<int, array{heading: string, sort_order?: string}> $rawSectionTexts
     * @return array{0: array<string, string>, 1: array{
     *     customerId: ?string, startDate: ?DateTimeImmutable, endDate: ?DateTimeImmutable,
     *     eventLocation: ?string, venueId: ?string, validUntil: ?DateTimeImmutable, note: ?string,
     *     items: array<int, array{articleId: string, quantity: int, unitPrice: string, lineNote: ?string, sortOrder: int}>,
     *     laborItems: array<int, array{laborTimeArticleId: string, userId: ?string, hours: string, unitRatePerHour: string}>,
     *     saleItems: array<int, array{articleId: string, inventoryNumberId: ?string, quantity: int, unitPrice: string, lineNote: ?string}>,
     *     freeTextItems: array<int, array{description: string, quantity: string, unitPrice: string, sortOrder: int}>,
     *     sectionTexts: array<int, array{heading: string, sortOrder: int}>,
     *     discountType: ?string, discountValue: ?string,
     *     rentalId: ?string,
     *     contactId: ?string,
     * }}
     */
    private function validateOfferInput(
        CustomerRepositoryInterface $customers,
        VenueRepositoryInterface $venues,
        ArticleRepositoryInterface $articles,
        LaborTimeArticleRepositoryInterface $laborTimeArticles,
        InventoryNumberRepositoryInterface $inventoryNumbers,
        SaleRepositoryInterface $sales,
        UserRepositoryInterface $users,
        string $customerId,
        string $rawStartDate,
        string $rawEndDate,
        string $eventLocation,
        string $rawValidUntil,
        string $note,
        array $rawItems,
        array $rawLaborItems,
        array $rawSaleItems,
        array $rawFreeTextItems = [],
        array $rawSectionTexts = [],
        ?string $rawDiscountType = null,
        ?string $rawDiscountValue = null,
        ?RentalRepositoryInterface $rentals = null,
        ?string $rawRentalId = null,
        ?CustomerContactRepositoryInterface $customerContacts = null,
        ?string $rawContactId = null,
    ): array {
        $errors = [];

        // Optionale Verknüpfung mit einer bestehenden Veranstaltung (Rental)
        // — siehe ValidatesInvoiceInput::validateInvoiceInput() für die
        // identische Begründung/Regel (bewusst dupliziert, gleiches Muster
        // wie der restliche Code dieser Traits).
        $rentalId = $rawRentalId !== null ? trim($rawRentalId) : '';
        if ($rentalId !== '' && $rentals !== null && $rentals->findById($rentalId) === null) {
            $errors['rentalId'] = 'Die gewählte Veranstaltung wurde nicht gefunden.';
            $rentalId = '';
        }

        // Ansprechpartner-Auswahl (Migration 104) — siehe
        // ValidatesInvoiceInput::validateInvoiceInput() für die identische
        // Begründung/Regel (bewusst dupliziert, gleiches Muster wie der
        // restliche Code dieser Traits).
        $contactId = $rawContactId !== null ? trim($rawContactId) : '';
        if ($contactId !== '' && $customerContacts !== null) {
            $contact = $customerContacts->findById($contactId);
            if ($contact === null || (trim($customerId) !== '' && $contact->customerId !== trim($customerId))) {
                $contactId = '';
            }
        }

        $customerId = trim($customerId);
        if ($customerId !== '' && $customers->findById($customerId) === null) {
            $errors['customerId'] = 'Der gewählte Kunde wurde nicht gefunden.';
        }

        [$resolvedLocation, $venueId] = VenueResolver::resolve($venues, $eventLocation);

        // Der Zeitraum ist optional (siehe Migration 102) — ein Angebot kann
        // auch unabhängig von einer konkreten Veranstaltung erstellt werden.
        // Nur wenn EINES der beiden Felder gefüllt ist, muss es gültig sein
        // bzw. müssen beide zusammen einen schlüssigen Zeitraum ergeben.
        $rawStartDateTrimmed = trim($rawStartDate);
        $rawEndDateTrimmed = trim($rawEndDate);

        $startDate = null;
        if ($rawStartDateTrimmed !== '') {
            $startDate = $this->parseOfferDate($rawStartDateTrimmed);
            if ($startDate === null) {
                $errors['startDate'] = 'Bitte ein gültiges Startdatum angeben.';
            }
        }

        $endDate = null;
        if ($rawEndDateTrimmed !== '') {
            $endDate = $this->parseOfferDate($rawEndDateTrimmed);
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

        $validUntil = null;
        $rawValidUntil = trim($rawValidUntil);
        if ($rawValidUntil !== '') {
            $validUntil = $this->parseOfferDate($rawValidUntil);
            if ($validUntil === null) {
                $errors['validUntil'] = 'Bitte ein gültiges Datum angeben.';
            }
        }

        $note = trim($note);

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

            // Siehe ValidatesInvoiceInput::validateInvoiceInput() für die
            // identische Begründung (Abrechnungseinheit je Position wählbar,
            // Fallback Stunde, wenn kein Tagessatz hinterlegt ist).
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

        // Kombirabatt: siehe ValidatesInvoiceInput::validateInvoiceInput() für
        // die identische Begründung/Regel (bewusst dupliziert statt geteilt,
        // gleiches Muster wie bei den übrigen Validates*Input-Traits).
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

        $saleItems = [];
        foreach ($rawSaleItems as $rawSaleItem) {
            $articleId = trim((string) ($rawSaleItem['article_id'] ?? ''));
            if ($articleId === '') {
                continue;
            }

            if ($articles->findById($articleId) === null) {
                $errors['saleItems'] = 'Mindestens eine Verkaufsposition enthält einen unbekannten Artikel.';

                continue;
            }

            $source = (string) ($rawSaleItem['source'] ?? '');
            $inventoryNumberId = null;
            $quantity = 1;

            if ($source === 'inventory_number') {
                $inventoryNumberId = trim((string) ($rawSaleItem['inventory_number_id'] ?? ''));
                if ($inventoryNumberId === '') {
                    $errors['saleItems'] = 'Bitte für jede Verkaufsposition aus dem Vermietpool ein konkretes Exemplar wählen.';

                    continue;
                }

                $inventoryNumber = $inventoryNumbers->findById($inventoryNumberId);
                if ($inventoryNumber === null || $inventoryNumber->articleId !== $articleId) {
                    $errors['saleItems'] = 'Mindestens eine Verkaufsposition verweist auf ein ungültiges Exemplar.';

                    continue;
                }

                if ($sales->findByInventoryNumber($inventoryNumberId) !== null) {
                    $errors['saleItems'] = 'Mindestens ein ausgewähltes Exemplar wurde bereits verkauft.';

                    continue;
                }
            } else {
                $quantity = filter_var(trim((string) ($rawSaleItem['quantity'] ?? '')), FILTER_VALIDATE_INT);
                if ($quantity === false || $quantity < 1) {
                    $errors['saleItems'] = 'Bitte für jede Verkaufsposition aus dem Verkaufsbestand eine Menge von mindestens 1 angeben.';

                    continue;
                }
            }

            $unitPrice = Money::normalize((string) ($rawSaleItem['unit_price'] ?? ''));
            if ($unitPrice === false || $unitPrice === null) {
                $errors['saleItems'] = 'Bitte für jede Verkaufsposition einen gültigen Einzelpreis angeben.';

                continue;
            }

            $lineNote = trim((string) ($rawSaleItem['line_note'] ?? ''));

            $saleItems[] = [
                'articleId' => $articleId,
                'inventoryNumberId' => $inventoryNumberId,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'lineNote' => $lineNote !== '' ? $lineNote : null,
            ];
        }

        // Freitext-Posten/Zwischentexte: siehe
        // ValidatesOrderConfirmationInput::validateOrderConfirmationInput()
        // für die identische Begründung (orderedRows()-Konzept, zuerst für
        // Auftragsbestätigungen eingeführt und hier auf Angebote übertragen).
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

        // Geschäftsregel: siehe ValidatesInvoiceInput::validateInvoiceInput()
        // für die identische Begründung (bewusst dupliziert, gleiches Muster
        // wie der restliche Code dieser beiden Traits). Verkaufspositionen
        // bleiben bei dieser Prüfung bewusst unberücksichtigt — sie haben
        // kein Verhältnis zu Kombirabatt/Zubehörpauschale, die sich nur auf
        // die Mietpreise/Freitext-Summe beziehen.
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
            'validUntil' => $validUntil,
            'note' => $note !== '' ? $note : null,
            'items' => $items,
            'laborItems' => $laborItems,
            'saleItems' => $saleItems,
            'freeTextItems' => $freeTextItems,
            'sectionTexts' => $sectionTexts,
            'discountType' => $discountType,
            'discountValue' => $discountValue,
            'rentalId' => $rentalId !== '' ? $rentalId : null,
            'contactId' => $contactId !== '' ? $contactId : null,
        ]];
    }

    private function parseOfferDate(string $value): ?DateTimeImmutable
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
}
